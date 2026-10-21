<?php

namespace WPML\Infrastructure\WordPress\Component\Translation\Application\Query;

use WPML\Core\Component\Translation\Application\Query\JobQueryInterface;
use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;
use WPML\Core\SharedKernel\Component\Translation\Domain\ReviewStatus;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;

class JobQuery implements JobQueryInterface {

  private $queryHandler;

  private $queryPrepare;


  public function __construct(
    QueryHandlerInterface $queryHandler,
    QueryPrepareInterface $queryPrepare
  ) {
    $this->queryHandler = $queryHandler;
    $this->queryPrepare = $queryPrepare;
  }


  public function hasAnyAutomatic(): bool {
    $query = "
        SELECT EXISTS(
          SELECT 1 
          FROM `{$this->queryPrepare->prefix()}icl_translate_job`
          WHERE automatic = 1
          )
          ";

    return (bool) $this->queryHandler->querySingle( $query );
  }


  public function countAutomaticInProgress(): int {
    $query = "		
          SELECT COUNT(jobs.job_id) 
          FROM {$this->queryPrepare->prefix()}icl_translate_job jobs
          INNER JOIN {$this->queryPrepare->prefix()}icl_translation_status translation_status 
              ON translation_status.rid = jobs.rid
          WHERE automatic = 1 AND translation_status.status IN (1, 2)
            AND jobs.job_id = (
              SELECT MAX( newest.job_id )
              FROM {$this->queryPrepare->prefix()}icl_translate_job newest
              WHERE newest.rid = jobs.rid
            )
          ";

    return (int) $this->queryHandler->querySingle( $query );
  }


  public function countNeedsReview(): int {
    $query = "
          SELECT COUNT(translation_status.translation_id)
          FROM {$this->queryPrepare->prefix()}icl_translation_status translation_status
          INNER JOIN {$this->queryPrepare->prefix()}icl_translations translations
              ON translations.translation_id = translation_status.translation_id
          INNER JOIN {$this->queryPrepare->prefix()}icl_translations original_translations
              ON original_translations.trid = translations.trid
              AND original_translations.element_type = translations.element_type
              AND original_translations.source_language_code IS NULL
              AND original_translations.element_id IS NOT NULL
              AND (
                translations.element_id IS NOT NULL
                OR translations.element_type LIKE 'package\_%%'
              )
          WHERE ( translation_status.review_status = %s AND translation_status.status = %d )
              OR ( translation_status.review_status = %s AND translation_status.status = %d )
          ";

    $preparedQuery = $this->queryPrepare->prepare(
      $query,
      ReviewStatus::NEEDS_REVIEW,
      TranslationStatus::COMPLETE,
      ReviewStatus::EDITING,
      TranslationStatus::IN_PROGRESS
    );

    return (int) $this->queryHandler->querySingle( $preparedQuery );
  }


  public function getInFlightChargedAutomatic(): array {
    $query = "
          SELECT SUM( CASE WHEN jobs.wpml_automatic_translation_costs IS NULL THEN 0 ELSE 1 END ) AS charged,
                 SUM( CASE WHEN jobs.wpml_automatic_translation_costs IS NULL THEN 1 ELSE 0 END ) AS unknown_costs,
                 SUM( CASE WHEN jobs.wpml_automatic_translation_costs IS NULL
                           THEN 0 ELSE COALESCE( jobs.wpml_words_to_translate_count, 0 ) END ) AS words
          FROM {$this->queryPrepare->prefix()}icl_translate_job jobs
          INNER JOIN {$this->queryPrepare->prefix()}icl_translation_status translation_status
              ON translation_status.rid = jobs.rid
          INNER JOIN (
              SELECT rid, MAX(job_id) AS max_job_id
                FROM {$this->queryPrepare->prefix()}icl_translate_job
               GROUP BY rid
          ) latest ON latest.rid = jobs.rid
                  AND latest.max_job_id = jobs.job_id
          WHERE jobs.automatic = 1
            AND jobs.editor = 'ate'
            AND jobs.editor_job_id > 0
            AND jobs.translated = 0
            AND translation_status.status IN (1, 2)
          ";

    $row = $this->queryHandler->queryOne( $query );

    if ( ! is_array( $row ) ) {
      return [
        'charged' => 0,
        'unknown' => 0,
        'words'   => 0,
      ];
    }

    return [
      'charged' => (int) ( $row['charged'] ?? 0 ),
      'unknown' => (int) ( $row['unknown_costs'] ?? 0 ),
      'words'   => (int) ( $row['words'] ?? 0 ),
    ];
  }


}
