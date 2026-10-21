<?php

namespace WPML\TM\ATE;


use WPML\Core\Component\Translation\Domain\Entity\JobError;
use WPML\Element\API\Languages;
use WPML\FP\Cast;
use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\TM\ATE\Review\ReviewStatus;

class Jobs {
	const LONGSTANDING_AT_ATE_SYNC_COUNT = 100;

	private function getLatestErrorJoinSQL() {
		global $wpdb;

		return "
			LEFT JOIN {$wpdb->prefix}icl_translate_unsolvable_jobs latest_error 
				ON latest_error.job_id = jobs.job_id
		";
	}

	private function getLatestPendingJobJoinSQL( $statusCount = 2 ) {
		global $wpdb;

		$statusPlaceholders = implode( ', ', array_fill( 0, max( 1, (int) $statusCount ), '%d' ) );

		return "
			INNER JOIN (
				SELECT pending_jobs.rid, MAX(pending_jobs.job_id) AS job_id
				FROM {$wpdb->prefix}icl_translate_job pending_jobs
				INNER JOIN {$wpdb->prefix}icl_translation_status pending_status
					ON pending_status.rid = pending_jobs.rid
				WHERE pending_status.status IN ( {$statusPlaceholders} )
				GROUP BY pending_jobs.rid
			) latest_job ON latest_job.job_id = jobs.job_id
		";
	}

	private function getErrorExclusionWhereSQL() {
		$excluded = [];
		foreach ( JobError::RETRY_LIMITS as $type => $limit ) {
			$excluded[] = sprintf(
				"( latest_error.error_type = '%s' AND latest_error.counter >= %d )",
				$type,
				$limit
			);
		}

		return "
			AND (
				latest_error.job_id IS NULL
				OR NOT ( " . implode( ' OR ', $excluded ) . " )
			)
		";
	}

	public function getCountOfAutomaticInProgress( $includeLongstanding = true ) {
		global $wpdb;

		$sql = "
				SELECT COUNT(jobs.job_id)
				FROM {$wpdb->prefix}icl_translate_job jobs
				INNER JOIN {$wpdb->prefix}icl_translation_status translation_status ON translation_status.rid = jobs.rid
				INNER JOIN {$wpdb->prefix}icl_translations translations ON translations.translation_id = translation_status.translation_id
				";

		if ( wpml_is_st_loaded() ) {
			$sql .= "
				LEFT JOIN {$wpdb->prefix}icl_translations original_translations ON
				    original_translations.trid = translations.trid AND original_translations.source_language_code IS NULL
				LEFT JOIN {$wpdb->prefix}icl_string_batches string_batches ON 
					string_batches.batch_id = original_translations.element_id AND translations.element_type = 'st-batch_strings'
        ";
		}

		$sql .= $this->getLatestPendingJobJoinSQL( 1 );

		$sql .= $this->getLatestErrorJoinSQL();

		$sql .= "
				WHERE jobs.automatic = 1
				AND jobs.editor = %s
				AND translation_status.status = %d
				AND translations.source_language_code = %s
		";

		$sql .= $this->getErrorExclusionWhereSQL();

		if ( ! $includeLongstanding ) {
			$sql .= " AND jobs.ate_sync_count < %d";

			return (int) $wpdb->get_var( $wpdb->prepare( $sql, ICL_TM_IN_PROGRESS, \WPML_TM_Editors::ATE, ICL_TM_IN_PROGRESS, Languages::getDefaultCode(), self::LONGSTANDING_AT_ATE_SYNC_COUNT ) );
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ICL_TM_IN_PROGRESS, \WPML_TM_Editors::ATE, ICL_TM_IN_PROGRESS, Languages::getDefaultCode() ) );
	}

	public function getCountOfInProgress() {
		global $wpdb;

		$sql = "
				SELECT COUNT(jobs.job_id)
				FROM {$wpdb->prefix}icl_translate_job jobs
				INNER JOIN {$wpdb->prefix}icl_translation_status translation_status ON translation_status.rid = jobs.rid
				INNER JOIN {$wpdb->prefix}icl_translations translations ON translations.translation_id = translation_status.translation_id
		";

		$sql .= $this->getLatestPendingJobJoinSQL( 1 );

		$sql .= $this->getLatestErrorJoinSQL();

		$sql .= "
				WHERE jobs.editor = %s
				AND translation_status.status = %d
		";

		$sql .= $this->getErrorExclusionWhereSQL();

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ICL_TM_IN_PROGRESS, \WPML_TM_Editors::ATE, ICL_TM_IN_PROGRESS ) );
	}

	public function getCountOfNeedsReview() {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(translation_status.translation_id)
				FROM {$wpdb->prefix}icl_translation_status translation_status
			INNER JOIN {$wpdb->prefix}icl_translations translations ON 
			    translations.translation_id = translation_status.translation_id
			INNER JOIN {$wpdb->prefix}icl_translations original_translations ON
			    original_translations.trid = translations.trid
			    AND original_translations.element_type = translations.element_type
			    AND original_translations.source_language_code IS NULL
			    AND original_translations.element_id IS NOT NULL
				AND (
					translations.element_id IS NOT NULL
					OR translations.element_type LIKE %s
				)
				WHERE ( translation_status.review_status = %s AND translation_status.status = %d ) OR
				      ( translation_status.review_status = %s AND translation_status.status = %d )
				",
				$wpdb->esc_like( 'package_' ) . '%',
				ReviewStatus::NEEDS_REVIEW,
				ICL_TM_COMPLETE,
				ReviewStatus::EDITING,
				ICL_TM_IN_PROGRESS
			)
		);
	}


	public function hasAny() {
		global $wpdb;

		$noOfRowsToFetch = 1;

		return boolval(
			$wpdb->get_var(
				$wpdb->prepare( "SELECT EXISTS(SELECT %d FROM {$wpdb->prefix}icl_translate_job)", $noOfRowsToFetch )
			)
		);
	}

	public function hasAnyToSync() {
		global $wpdb;

		$sql = "
				SELECT jobs.job_id
				FROM {$wpdb->prefix}icl_translate_job jobs
				INNER JOIN {$wpdb->prefix}icl_translation_status translation_status ON translation_status.rid = jobs.rid
				INNER JOIN {$wpdb->prefix}icl_translations translations ON translations.translation_id = translation_status.translation_id
		";

		$sql .= $this->getLatestPendingJobJoinSQL( 1 );

		$sql .= $this->getLatestErrorJoinSQL();

		$sql .= "
				WHERE jobs.editor = %s
				AND translation_status.status = %d
		";

		$sql .= $this->getErrorExclusionWhereSQL();

		$sql .= " LIMIT 1";

		return (bool) $wpdb->get_var( $wpdb->prepare( $sql, ICL_TM_IN_PROGRESS, \WPML_TM_Editors::ATE, ICL_TM_IN_PROGRESS ) );
	}

	public function getATEJobIdsToSync( $includeManualAndLongstandingJobs = true ) {
		global $wpdb;

		$sql = "
				SELECT jobs.editor_job_id
				FROM {$wpdb->prefix}icl_translate_job jobs
			    INNER JOIN {$wpdb->prefix}icl_translation_status translation_status
			        ON translation_status.rid = jobs.rid
			    INNER JOIN {$wpdb->prefix}icl_translations translations
			        ON translations.translation_id = translation_status.translation_id
		";

		$sql .= $this->getLatestPendingJobJoinSQL();

		$sql .= $this->getLatestErrorJoinSQL();

		$sql .= "
				WHERE jobs.editor = %s
				AND ( translation_status.status = %d OR translation_status.status = %d )
		";

		$sql .= $this->getErrorExclusionWhereSQL();

		if ( ! $includeManualAndLongstandingJobs ) {
			$sql .= " AND jobs.ate_sync_count < %d AND jobs.automatic = 1";

			return $wpdb->get_col(
				$wpdb->prepare(
					$sql,
					ICL_TM_IN_PROGRESS,
					ICL_TM_WAITING_FOR_TRANSLATOR,
					\WPML_TM_Editors::ATE,
					ICL_TM_IN_PROGRESS,
					ICL_TM_WAITING_FOR_TRANSLATOR,
					self::LONGSTANDING_AT_ATE_SYNC_COUNT
				)
			);
		}

		return $wpdb->get_col(
			$wpdb->prepare(
				$sql,
				ICL_TM_IN_PROGRESS,
				ICL_TM_WAITING_FOR_TRANSLATOR,
				\WPML_TM_Editors::ATE,
				ICL_TM_IN_PROGRESS,
				ICL_TM_WAITING_FOR_TRANSLATOR
			)
		);
	}

	public function getATEJobIdsToSyncWithElementIds( $includeManualAndLongstandingJobs = true ): array {
		global $wpdb;

		$sql = "
				SELECT 
					jobs.editor_job_id,
					translations.element_type,
					original_translations.element_id
				FROM {$wpdb->prefix}icl_translate_job jobs
			    INNER JOIN {$wpdb->prefix}icl_translation_status translation_status
			        ON translation_status.rid = jobs.rid
			    INNER JOIN {$wpdb->prefix}icl_translations translations
			        ON translations.translation_id = translation_status.translation_id
			    INNER JOIN {$wpdb->prefix}icl_translations original_translations
			        ON original_translations.trid = translations.trid
			        AND original_translations.source_language_code IS NULL
		";

		$sql .= $this->getLatestPendingJobJoinSQL();

		$sql .= $this->getLatestErrorJoinSQL();

		$sql .= "
				WHERE jobs.editor = %s
				AND ( translation_status.status = %d OR translation_status.status = %d )
		";

		$sql .= $this->getErrorExclusionWhereSQL();

		$orderBySQL = ' ORDER BY jobs.ate_sync_count ASC, jobs.job_id ASC';

		if ( ! $includeManualAndLongstandingJobs ) {
			$sql    .= ' AND jobs.ate_sync_count < %d AND jobs.automatic = 1' . $orderBySQL;
			$results = $wpdb->get_results(
				$wpdb->prepare(
					$sql,
					ICL_TM_IN_PROGRESS,
					ICL_TM_WAITING_FOR_TRANSLATOR,
					\WPML_TM_Editors::ATE,
					ICL_TM_IN_PROGRESS,
					ICL_TM_WAITING_FOR_TRANSLATOR,
					self::LONGSTANDING_AT_ATE_SYNC_COUNT
				)
			);
		} else {
			$sql    .= $orderBySQL;
			$results = $wpdb->get_results(
				$wpdb->prepare(
					$sql,
					ICL_TM_IN_PROGRESS,
					ICL_TM_WAITING_FOR_TRANSLATOR,
					\WPML_TM_Editors::ATE,
					ICL_TM_IN_PROGRESS,
					ICL_TM_WAITING_FOR_TRANSLATOR
				)
			);
		}

		return $this->groupJobsByElementType( $results ?: [] );
	}

	private function groupJobsByElementType( array $results ): array {
		$ateJobIds  = [];
		$postIds    = [];
		$stringIds  = [];
		$packageIds = [];
		$termIds    = [];

		foreach ( $results as $row ) {
			$ateJobIds[] = (int) $row->editor_job_id;
			$elementId   = (int) $row->element_id;
			$elementType = $row->element_type;

			if ( strpos( $elementType, 'post_' ) === 0 ) {
				$postIds[] = $elementId;
			} elseif ( 'st-batch_strings' === $elementType ) {
				$stringIds[] = $elementId;
			} elseif ( strpos( $elementType, 'package_' ) === 0 ) {
				$packageIds[] = $elementId;
			} elseif ( strpos( $elementType, 'tax_' ) === 0 ) {
				$termIds[] = $elementId;
			}
		}

		return [
			'ateJobIds'  => $ateJobIds,
			'postIds'    => array_unique( $postIds ),
			'stringIds'  => array_unique( $stringIds ),
			'packageIds' => array_unique( $packageIds ),
			'termIds'    => array_unique( $termIds ),
		];
	}
}
