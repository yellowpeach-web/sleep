<?php

namespace WPML\Infrastructure\WordPress\Component\Translation\Application\Query;

use WPML\Core\Component\Translation\Application\Query\UnsolvableJobsQueryInterface;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;

class UnsolvableJobsQuery implements UnsolvableJobsQueryInterface {

  private $queryHandler;

  private $queryPrepare;


  public function __construct(
    QueryHandlerInterface $queryHandler,
    QueryPrepareInterface $queryPrepare
  ) {
    $this->queryHandler = $queryHandler;
    $this->queryPrepare = $queryPrepare;
  }


  public function getUnsolvableJobs(): array {
    $query = "
			SELECT
				job.job_id AS jobId,
				error.ate_job_id AS ateJobId,
				status.status AS status,
				error.error_message AS message,
				error.error_type AS errorType,
				error.error_data AS errorData,
				resend_anchor.element_type AS elementType,
				resend_anchor.element_id AS originalElementId,
				resend_translation.element_id AS elementId,
				(
					resend_anchor.translation_id IS NOT NULL
					AND (
						resend_anchor.element_type NOT LIKE 'tax\\_%'
						OR resend_term.term_taxonomy_id IS NOT NULL
					)
				) AS canResend
			FROM {$this->queryPrepare->prefix()}icl_translate_unsolvable_jobs error
			INNER JOIN {$this->queryPrepare->prefix()}icl_translate_job job
				ON job.job_id = error.job_id
			INNER JOIN {$this->queryPrepare->prefix()}icl_translation_status status
				ON status.rid = job.rid
			INNER JOIN (
				SELECT rid, MAX(job_id) AS latest_job_id
				FROM {$this->queryPrepare->prefix()}icl_translate_job
				WHERE editor = 'ATE'
				GROUP BY rid
			) latest_jobs
				ON latest_jobs.rid = job.rid
				AND latest_jobs.latest_job_id = job.job_id
			-- Orphan detection: a job can only be resent when its translation can be
			-- rebuilt from a source, i.e. the target translation row exists (with a
			-- source language) AND its trid still has an original/anchor row
			-- (source_language_code IS NULL). This mirrors the INNER JOINs that
			-- TranslationQuery uses to load a job for resending; when the anchor is
			-- missing (e.g. left behind by a migration) resending is impossible.
			LEFT JOIN {$this->queryPrepare->prefix()}icl_translations resend_translation
				ON resend_translation.translation_id = status.translation_id
				AND resend_translation.source_language_code IS NOT NULL
			LEFT JOIN {$this->queryPrepare->prefix()}icl_translations resend_anchor
				ON resend_anchor.trid = resend_translation.trid
				AND resend_anchor.source_language_code IS NULL
			-- Second orphan class, for taxonomy terms only (wpmldev-8105): the
			-- anchor row can survive a term that is already gone, and `canResend`
			-- has to stay truthful because ResendUnsolvableJobsService cancels
			-- every id it keeps BEFORE anything is sent. A term job that cannot
			-- be dispatched must therefore be filtered out here, or cancelJobs()
			-- resets it to NOT TRANSLATED and the sender then declines it - the
			-- job ends up worse off than if it had never been offered.
			LEFT JOIN {$this->queryPrepare->prefix()}term_taxonomy resend_term
				ON resend_anchor.element_type LIKE 'tax\\_%'
				AND resend_term.term_taxonomy_id = resend_anchor.element_id
			-- wpmldev-8049: a job the product gave up on carries the terminal
			-- status ATE_UNSOLVABLE. The second arm keeps rows written before
			-- that status existed (an exhausted counter on a row still 'in
			-- progress') visible until they are resent or discarded.
			WHERE job.editor = 'ATE'
			AND (
				status.status = " . TranslationStatus::ATE_UNSOLVABLE . "
				OR (
					(
						error.error_type = 'SyncError'
						OR (error.error_type = 'DownloadError' AND error.counter >= 3)
						OR (error.error_type = 'ApplyError' AND error.counter >= 1)
					)
					AND status.status IN (1, 2)
				)
			)
		";

    try {
      $rows = $this->queryHandler->query( $query );

      return $this->mapResults( $rows->getResults() );
    } catch ( DatabaseErrorException $e ) {
      return [];
    }
  }


  private function mapResults( array $rows ): array {
    return array_map(
      function ( $row ) {
        $errorData = is_string( $row['errorData'] ) ? json_decode( $row['errorData'], true ) : [];
        $errorData = is_array( $errorData ) ? $errorData : [];

        $jobId     = isset( $row['jobId'] ) && is_numeric( $row['jobId'] ) ? (int) $row['jobId'] : 0;
        $ateJobId  = isset( $row['ateJobId'] ) && is_numeric( $row['ateJobId'] ) ? (int) $row['ateJobId'] : 0;
        $status    = isset( $row['status'] ) && is_numeric( $row['status'] ) ? (int) $row['status'] : 0;
        $ateStatus = isset( $errorData['ateStatus'] ) && is_numeric( $errorData['ateStatus'] )
          ? (int) $errorData['ateStatus']
          : 0;
        $message   = isset( $row['message'] ) && is_string( $row['message'] ) && $row['message'] !== ''
          ? $row['message']
          : 'Job marked as unsolvable by ATE';
        $errorType = isset( $row['errorType'] ) && is_string( $row['errorType'] )
          ? $row['errorType']
          : 'Unknown';

        return [
          'jobId'        => $jobId,
          'ateJobId'     => $ateJobId,
          'ateStatus'    => $ateStatus,
          'status'       => $status,
          'isUnsolvable' => true,
          'message'      => $message,
          'errorType'    => $errorType,
          'errorData'    => isset( $row['errorData'] ) && is_string( $row['errorData'] ) ? $row['errorData'] : null,
          'canResend'    => ! empty( $row['canResend'] ) && $this->isDispatchableType( $row ),
          'originalElementId' => $this->idFromRowOrErrorData( $row, 'originalElementId', $errorData, 'original_doc_id' ),
          'elementId'         => $this->idFromRowOrErrorData( $row, 'elementId', $errorData, 'elementId' ),
        ];
      },
      $rows
    );
  }


  private function isDispatchableType( array $row ): bool {
    $elementType = isset( $row['elementType'] ) && is_string( $row['elementType'] )
      ? $row['elementType']
      : '';

    if ( 0 !== strpos( $elementType, 'tax_' ) ) {
      return true;
    }

    return taxonomy_exists( substr( $elementType, strlen( 'tax_' ) ) );
  }


  private function idFromRowOrErrorData( array $row, string $rowKey, array $errorData, string $errorDataKey ) {
    if ( isset( $row[ $rowKey ] ) && is_numeric( $row[ $rowKey ] ) && (int) $row[ $rowKey ] > 0 ) {
      return (int) $row[ $rowKey ];
    }
    $fallback = $this->getErrorDataValue( $errorData, $errorDataKey );

    return is_numeric( $fallback ) && (int) $fallback > 0 ? (int) $fallback : null;
  }


  private function getErrorDataValue( array $errorData, string $key ) {
    if ( ! isset( $errorData['jobData'] ) || ! is_array( $errorData['jobData'] ) ) {
      return null;
    }
    return $errorData['jobData'][$key] ?? null;
  }


}
