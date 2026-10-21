<?php

namespace WPML\Infrastructure\WordPress\Component\Cdt\Query;

use WPML\Core\Component\Cdt\Application\Query\AtePollLimitQueryInterface;
use WPML\Core\Component\Cdt\Application\Query\HealthStatementsQueryInterface;
use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;

class HealthStatementsQuery implements HealthStatementsQueryInterface {

  const JOBS_TABLE         = 'icl_translate_job';
  const STATUS_TABLE       = 'icl_translation_status';
  const UNSOLVABLE_TABLE   = 'icl_translate_unsolvable_jobs';
  const TRANSLATIONS_TABLE = 'icl_translations';
  const POSTS_TABLE        = 'posts';
  const EDITOR_ATE         = 'ate';
  const EDITOR_WP          = 'wp';

  private $queryHandler;

  private $queryPrepare;

  private $pollLimit;


  public function __construct(
    QueryHandlerInterface $queryHandler,
    QueryPrepareInterface $queryPrepare,
    AtePollLimitQueryInterface $pollLimit
  ) {
    $this->queryHandler = $queryHandler;
    $this->queryPrepare = $queryPrepare;
    $this->pollLimit    = $pollLimit;
  }


  public function getNewestError(): array {
    $table = $this->queryPrepare->prefix() . self::UNSOLVABLE_TABLE;

    $row = $this->queryHandler->queryOne(
      "SELECT error_type, last_update FROM {$table} ORDER BY last_update DESC LIMIT 1"
    );

    if ( ! is_array( $row ) || ! isset( $row['error_type'] ) ) {
      return [ 'type' => null, 'at' => null ];
    }

    $at = null;
    if ( isset( $row['last_update'] ) && is_string( $row['last_update'] ) && $row['last_update'] !== '' ) {
      $gmt = get_gmt_from_date( $row['last_update'], 'U' );
      $at  = is_numeric( $gmt ) && (int) $gmt > 0 ? (int) $gmt : null;
    }

    return [ 'type' => is_scalar( $row['error_type'] ) ? (string) $row['error_type'] : null, 'at' => $at ];
  }


  public function getStuckAndDeliveries( int $since ): array {
    $jobs   = $this->queryPrepare->prefix() . self::JOBS_TABLE;
    $status = $this->queryPrepare->prefix() . self::STATUS_TABLE;
    $open   = TranslationStatus::WAITING_FOR_TRANSLATOR . ', ' . TranslationStatus::IN_PROGRESS;
    $limit  = $this->pollLimit->get();
    $sinceDate = gmdate( 'Y-m-d H:i:s', $since );

    $sql = $this->queryPrepare->prepare(
      "SELECT
          CASE WHEN status.status IN ({$open}) AND jobs.ate_sync_count >= {$limit} THEN 1 ELSE 0 END AS stuck,
          CASE WHEN jobs.automatic = 1 AND jobs.completed_date >= %s AND jobs.completed_date >= status.timestamp
            THEN TIMESTAMPDIFF(SECOND, status.timestamp, jobs.completed_date) ELSE NULL END AS delivery_seconds
        FROM ( SELECT rid, MAX(job_id) AS job_id FROM {$jobs} GROUP BY rid ) latest
        INNER JOIN {$jobs} jobs ON jobs.job_id = latest.job_id
        INNER JOIN {$status} status ON status.rid = jobs.rid
        WHERE jobs.editor = %s
          AND (
            ( status.status IN ({$open}) AND jobs.ate_sync_count >= {$limit} )
            OR ( jobs.automatic = 1 AND jobs.completed_date >= %s AND jobs.completed_date >= status.timestamp )
          )",
      $sinceDate,
      self::EDITOR_ATE,
      $sinceDate
    );

    $stuck   = 0;
    $seconds = [];
    foreach ( $this->queryHandler->query( $sql )->getResults() as $row ) {
      if ( ! is_array( $row ) ) {
        continue;
      }
      if ( ! empty( $row['stuck'] ) ) {
        $stuck++;
      }
      if ( isset( $row['delivery_seconds'] ) && is_numeric( $row['delivery_seconds'] ) ) {
        $seconds[] = (int) $row['delivery_seconds'];
      }
    }

    return [ 'stuck' => $stuck, 'deliverySeconds' => $seconds ];
  }


  public function getLastManualTranslationAt(): ?int {
    $prefix       = $this->queryPrepare->prefix();
    $jobs         = $prefix . self::JOBS_TABLE;
    $status       = $prefix . self::STATUS_TABLE;
    $translations = $prefix . self::TRANSLATIONS_TABLE;
    $posts        = $prefix . self::POSTS_TABLE;

    return self::utc(
      $this->queryHandler->querySingle(
        "SELECT MAX(d) FROM (
            SELECT MAX(j.completed_date) AS d FROM {$jobs} j
             WHERE j.completed_date IS NOT NULL AND j.automatic = 0 AND j.created_by_translate_everything = 0
            UNION ALL
            SELECT MAX(p.post_modified_gmt) FROM {$jobs} j
              JOIN {$status} s ON s.rid = j.rid
              JOIN {$translations} t ON t.translation_id = s.translation_id AND t.element_type LIKE 'post\\_%'
              JOIN {$posts} p ON p.ID = t.element_id
             WHERE j.editor = '" . self::EDITOR_WP . "' AND j.translated = 1 AND j.completed_date IS NULL
               AND j.translator_id > 0 AND j.automatic = 0 AND j.created_by_translate_everything = 0
          ) u"
      )
    );
  }


  public function getAutoTranslationRange(): array {
    $jobs = $this->queryPrepare->prefix() . self::JOBS_TABLE;

    $row = $this->queryHandler->queryOne(
      $this->queryPrepare->prepare(
        "SELECT MIN(completed_date) AS first, MAX(completed_date) AS last FROM {$jobs}
          WHERE completed_date IS NOT NULL AND automatic = 1 AND editor = %s",
        self::EDITOR_ATE
      )
    );

    return [
      'first' => is_array( $row ) ? self::utc( $row['first'] ?? null ) : null,
      'last'  => is_array( $row ) ? self::utc( $row['last'] ?? null ) : null,
    ];
  }


  private static function utc( $datetime ): ?int {
    if ( ! is_string( $datetime ) || $datetime === '' ) {
      return null;
    }

    $timestamp = strtotime( $datetime . ' UTC' );

    return $timestamp !== false && $timestamp > 0 ? $timestamp : null;
  }


}
