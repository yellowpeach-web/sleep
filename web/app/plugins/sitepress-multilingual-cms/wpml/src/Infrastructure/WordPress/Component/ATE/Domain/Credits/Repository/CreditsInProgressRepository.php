<?php
namespace WPML\Infrastructure\WordPress\Component\ATE\Domain\Credits\Repository;

use WPML\Core\Component\ATE\Domain\Credits\Repository\CreditsInProgressRepositoryInterface;
use WPML\Core\Component\WordsToTranslate\Domain\Job\Query\TranslationEngineQueryInterface;

class CreditsInProgressRepository implements CreditsInProgressRepositoryInterface {
  const OPTION_LOWEST_RELEVANT_JOB_ID = '_wpml_credits_in_progress_lowest_job_id';

  private $engineQuery;


  public function __construct( ?TranslationEngineQueryInterface $engineQuery = null ) {
    $this->engineQuery = $engineQuery;
  }


  public function getCreditsInProgressCount( $statusesInProgress ) {
    $wpdb = $GLOBALS['wpdb'];

    $lowestRelevantId = get_option( self::OPTION_LOWEST_RELEVANT_JOB_ID, 0 );
    $maxJobId = $wpdb->get_var( "SELECT MAX(job_id) FROM {$wpdb->prefix}icl_translate_job;" );

    if ( ! is_numeric( $maxJobId ) || $lowestRelevantId > (int) $maxJobId ) {
      return 0;
    }

    $statusesPlaceholders = implode(
      ', ',
      array_fill( 0, count( $statusesInProgress ), '%d' )
    );


    $from = $wpdb->prepare(
      "FROM {$wpdb->prefix}icl_translate_job j
        INNER JOIN {$wpdb->prefix}icl_translation_status ts
               ON ts.rid = j.rid
        LEFT JOIN {$wpdb->prefix}icl_translations translations
               ON translations.translation_id = ts.translation_id
        INNER JOIN (
            SELECT rid, MAX(job_id) AS max_job_id
            FROM {$wpdb->prefix}icl_translate_job
            WHERE job_id >= %d
            GROUP BY rid
        ) latest ON j.rid = latest.rid
                AND j.job_id = latest.max_job_id
        WHERE j.job_id >= %d
          AND j.automatic = 1
          AND j.editor = 'ate'
          AND ts.status IN ($statusesPlaceholders)",
      array_merge( [ $lowestRelevantId, $lowestRelevantId ], $statusesInProgress )
    );

    if ( ! is_string( $from ) ) {
      return 0;
    }

    $q = $wpdb->get_row(
      "SELECT
          SUM(j.wpml_automatic_translation_costs) as creditsInProgress,
          MIN(j.job_id) as newLowestRelevantId
        $from"
    );

    if ( $wpdb->last_error ) {
      return 0;
    }

    $credits = ( isset( $q->creditsInProgress ) ? (int) $q->creditsInProgress : 0 )
      + $this->legacyTermJobsCredits( $wpdb, $from );

    if ( isset( $q->newLowestRelevantId ) && is_numeric( $q->newLowestRelevantId ) ) {
      $newLowestJobId = (int) $q->newLowestRelevantId;
      if ( $newLowestJobId > $lowestRelevantId ) {
        update_option( self::OPTION_LOWEST_RELEVANT_JOB_ID, $newLowestJobId, false );
      }

    } else if ( (int) $maxJobId > $lowestRelevantId ) {
      update_option( self::OPTION_LOWEST_RELEVANT_JOB_ID, (int) $maxJobId + 1, false );
    }

    return $credits;
  }


  private function legacyTermJobsCredits( $wpdb, $from ) {
    $rows = $wpdb->get_results(
      "SELECT translations.source_language_code AS sourceLang,
          translations.language_code AS targetLang,
          SUM(j.wpml_words_to_translate_count) AS words
        $from
          AND j.wpml_automatic_translation_costs IS NULL
          AND translations.element_type LIKE 'tax\\_%'
        GROUP BY translations.source_language_code, translations.language_code",
      ARRAY_A
    );

    if ( $wpdb->last_error || ! is_array( $rows ) || ! $rows ) {
      return 0;
    }

    $engineQuery = $this->engineQuery();
    if ( ! $engineQuery ) {
      return 0;
    }

    $credits = 0;
    foreach ( $rows as $row ) {
      try {
        $perWord = $engineQuery->getCostsPerWordForLang(
          $row['targetLang'],
          $row['sourceLang'] ?: null
        );
      } catch ( \Throwable $e ) {
        $perWord = false;
      }

      if ( false !== $perWord ) {
        $credits += (int) $row['words'] * $perWord;
      }
    }

    return $credits;
  }


  private function engineQuery() {
    if ( ! $this->engineQuery ) {
      try {
        $wpml_dic = $GLOBALS['wpml_dic'] ?? null;
        $this->engineQuery = $wpml_dic ? $wpml_dic->make( TranslationEngineQueryInterface::class ) : null;
      } catch ( \Throwable $e ) {
        return null;
      }
    }

    return $this->engineQuery;
  }


}
