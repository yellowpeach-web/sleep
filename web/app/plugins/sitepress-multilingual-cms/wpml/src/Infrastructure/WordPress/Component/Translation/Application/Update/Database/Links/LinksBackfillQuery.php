<?php

namespace WPML\Infrastructure\WordPress\Component\Translation\Application\Update\Database\Links;

use WPML\Core\Component\Translation\Application\Update\Database\Links\LinksBackfillQueryInterface;

class LinksBackfillQuery implements LinksBackfillQueryInterface {
  const TYPE_POST = 'post';
  const TYPE_TERM = 'term';


  public function getOriginalPostIdsWithTranslations( int $afterId, int $limit ) {
    $wpdb = $GLOBALS['wpdb'];

    $ids = $wpdb->get_col(
      $wpdb->prepare(
        "SELECT DISTINCT original.element_id
        FROM {$wpdb->prefix}icl_translations AS original
        INNER JOIN {$wpdb->prefix}icl_translations AS translation
          ON translation.trid = original.trid
          AND translation.translation_id != original.translation_id
          AND translation.element_id IS NOT NULL
        WHERE original.source_language_code IS NULL
        AND original.element_type LIKE %s
        AND original.element_id > %d
        ORDER BY original.element_id ASC
        LIMIT %d",
        $wpdb->esc_like( 'post_' ) . '%',
        $afterId,
        $limit
      )
    );

    if ( ! empty( $wpdb->last_error ) || ! is_array( $ids ) ) {
      return false;
    }

    return array_map( 'intval', $ids );
  }


  public function countOriginalPostsWithTranslations( $upToId = null ) {
    $wpdb = $GLOBALS['wpdb'];

    $count = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT COUNT( DISTINCT original.element_id )
        FROM {$wpdb->prefix}icl_translations AS original
        INNER JOIN {$wpdb->prefix}icl_translations AS translation
          ON translation.trid = original.trid
          AND translation.translation_id != original.translation_id
          AND translation.element_id IS NOT NULL
        WHERE original.source_language_code IS NULL
        AND original.element_type LIKE %s
        AND original.element_id <= %d",
        $wpdb->esc_like( 'post_' ) . '%',
        $upToId === null ? PHP_INT_MAX : $upToId
      )
    );

    if ( ! empty( $wpdb->last_error ) || ! is_numeric( $count ) ) {
      return false;
    }

    return (int) $count;
  }


  public function getPostTranslationIds( int $originalId ) {
    $wpdb = $GLOBALS['wpdb'];

    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT translation.language_code AS languageCode, translation.element_id AS elementId
        FROM {$wpdb->prefix}icl_translations AS original
        INNER JOIN {$wpdb->prefix}icl_translations AS translation
          ON translation.trid = original.trid
          AND translation.translation_id != original.translation_id
          AND translation.element_id IS NOT NULL
        WHERE original.element_id = %d
        AND original.element_type LIKE %s
        AND original.source_language_code IS NULL",
        $originalId,
        $wpdb->esc_like( 'post_' ) . '%'
      )
    );

    $ids = [];
    if ( is_array( $rows ) ) {
      foreach ( $rows as $row ) {
        $ids[ (string) $row->languageCode ] = (int) $row->elementId;
      }
    }

    return $ids;
  }


  public function hasTranslation( int $id, string $type, string $languageCode ) {
    $wpdb = $GLOBALS['wpdb'];

    switch ( $type ) {
      case self::TYPE_POST:
        $translationId = $wpdb->get_var(
          $wpdb->prepare(
            "SELECT translation.element_id
            FROM {$wpdb->prefix}icl_translations AS original
            INNER JOIN {$wpdb->prefix}icl_translations AS translation
              ON translation.trid = original.trid
              AND translation.translation_id != original.translation_id
            WHERE original.element_id = %d
            AND original.element_type LIKE %s
            AND translation.language_code = %s
            AND translation.element_id IS NOT NULL
            LIMIT 1",
            $id,
            $wpdb->esc_like( 'post_' ) . '%',
            $languageCode
          )
        );
        break;
      case self::TYPE_TERM:
        $translationId = $wpdb->get_var(
          $wpdb->prepare(
            "SELECT translation.element_id
            FROM {$wpdb->term_taxonomy} AS term_taxonomy
            INNER JOIN {$wpdb->prefix}icl_translations AS original
              ON original.element_id = term_taxonomy.term_taxonomy_id
              AND original.element_type = CONCAT( 'tax_', term_taxonomy.taxonomy )
            INNER JOIN {$wpdb->prefix}icl_translations AS translation
              ON translation.trid = original.trid
              AND translation.translation_id != original.translation_id
            WHERE term_taxonomy.term_id = %d
            AND translation.language_code = %s
            AND translation.element_id IS NOT NULL
            LIMIT 1",
            $id,
            $languageCode
          )
        );
        break;
      default:
        return false;
    }

    return $translationId !== null;
  }


}
