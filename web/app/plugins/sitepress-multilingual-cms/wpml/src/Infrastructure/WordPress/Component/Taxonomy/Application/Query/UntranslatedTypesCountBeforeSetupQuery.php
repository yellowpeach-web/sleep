<?php

namespace WPML\Infrastructure\WordPress\Component\Taxonomy\Application\Query;

use WPML\Core\SharedKernel\Component\Item\Application\Query\Dto\UntranslatedTypeCountDto;
use WPML\Core\SharedKernel\Component\Item\Application\Query\UntranslatedTypesCountQueryInterface;
use WPML\Core\SharedKernel\Component\Language\Application\Query\Dto\LanguageDto;
use WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface;
use WPML\Core\SharedKernel\Component\Taxonomy\Application\Query\Dto\TaxonomyDto;
use WPML\Core\SharedKernel\Component\Taxonomy\Application\Query\TranslatableTaxonomiesQueryInterface;

class UntranslatedTypesCountBeforeSetupQuery implements UntranslatedTypesCountQueryInterface {

  private $taxonomiesQuery;

  private $languagesQuery;

  private $wpdb;


  public function __construct(
    TranslatableTaxonomiesQueryInterface $taxonomiesQuery,
    LanguagesQueryInterface $languagesQuery,
    $wpdb = null
  ) {
    $this->taxonomiesQuery = $taxonomiesQuery;
    $this->languagesQuery  = $languagesQuery;
    $this->wpdb            = $wpdb ?: $GLOBALS['wpdb'];
  }


  public function forKind() {
    return UntranslatedTypesCountQueryInterface::KIND_TAXONOMY;
  }


  public function get( array $queryData = [] ): array {
    $taxonomies = $this->taxonomiesQuery->getTranslatable();
    if ( ! $taxonomies ) {
      return [];
    }

    $counts = $this->countTermsPerTaxonomy( $taxonomies );

    return array_map(
      function ( TaxonomyDto $taxonomy ) use ( $counts ) {
        return new UntranslatedTypeCountDto(
          $taxonomy->getPlural(),
          $taxonomy->getSingular(),
          $counts[ $taxonomy->getId() ] ?? 0,
          UntranslatedTypesCountQueryInterface::KIND_TAXONOMY,
          'tax_' . $taxonomy->getId()
        );
      },
      $taxonomies
    );
  }


  public function getSomeIds( $numberOfIdsToFetch, $offset, $type = '' ) {
    $slugs = $this->taxonomySlugs();
    if ( ! $slugs ) {
      return [];
    }

    $limit     = absint( $numberOfIdsToFetch );
    $offset    = absint( $offset );
    $join      = $this->translationJoin();
    $condition = $this->needsTranslationCondition();
    $wpdb      = $this->wpdb;

    $ids = $wpdb->get_col(
      $wpdb->prepare(
        "SELECT tt.term_taxonomy_id
       FROM {$wpdb->term_taxonomy} AS tt
       {$join}
       WHERE tt.taxonomy IN (" . implode( ',', array_fill( 0, count( $slugs ), '%s' ) ) . ")
         AND {$condition['sql']}
       ORDER BY tt.term_taxonomy_id ASC
       LIMIT %d OFFSET %d",
        array_merge( $slugs, $condition['args'], [ $limit, $offset ] )
      )
    );

    return array_map( 'intval', $ids );
  }


  private function translationJoin(): string {
    return "LEFT JOIN {$this->wpdb->prefix}icl_translations AS itr
              ON itr.element_id = tt.term_taxonomy_id
             AND itr.element_type = CONCAT( 'tax_', tt.taxonomy )";
  }


  private function needsTranslationCondition(): array {
    $conditions = [
      '( itr.translation_id IS NULL OR itr.source_language_code IS NULL )',
    ];
    $args       = [];

    $secondaryCount    = count( $this->languagesQuery->getSecondary() );
    $translatableCodes = array_values(
      array_map(
        function ( LanguageDto $language ) {
          return $language->getCode();
        },
        $this->languagesQuery->getSecondaryTranslatable()
      )
    );
    if ( $secondaryCount > 0 && ! $translatableCodes ) {
      $conditions[] = '0 = 1';
    } elseif ( $secondaryCount > 0 ) {
      $conditions[] = "(
        itr.trid IS NULL
        OR (
          SELECT COUNT(*)
          FROM {$this->wpdb->prefix}icl_translations AS sibling
          WHERE sibling.trid = itr.trid
            AND sibling.source_language_code IS NOT NULL
            AND sibling.language_code IN ( " . implode( ',', array_fill( 0, count( $translatableCodes ), '%s' ) ) . ' )
        ) < %d
      )';
      $args         = array_merge( $args, $translatableCodes, [ count( $translatableCodes ) ] );
    }

    $wpmlOwned = $this->termTaxonomyIdsWpmlTranslatesItself();
    if ( $wpmlOwned ) {
      $conditions[] = 'tt.term_taxonomy_id NOT IN ( ' . implode( ',', array_fill( 0, count( $wpmlOwned ), '%d' ) ) . ' )';
      $args         = array_merge( $args, $wpmlOwned );
    }

    return [
      'sql'  => '( ' . implode( ' AND ', $conditions ) . ' )',
      'args' => $args,
    ];
  }


  private function termTaxonomyIdsWpmlTranslatesItself(): array {
    $sitepress = $GLOBALS['sitepress'] ?? null;

    $ids = [];

    if ( is_object( $sitepress ) && method_exists( $sitepress, 'get_setting' ) ) {
      foreach ( (array) $sitepress->get_setting( 'default_categories', [] ) as $termTaxonomyId ) {
        $termTaxonomyId = (int) $termTaxonomyId;
        if ( $termTaxonomyId > 0 ) {
          $ids[] = $termTaxonomyId;
        }
      }
    }

    $defaultCategory       = get_option( 'default_category' );
    $defaultCategoryTermId = is_numeric( $defaultCategory ) ? (int) $defaultCategory : 0;
    if ( $defaultCategoryTermId > 0 ) {
      $term = get_term( $defaultCategoryTermId, 'category' );

      if ( $term instanceof \WP_Term ) {
        $ids[] = $term->term_taxonomy_id;
      }
    }

    return array_values( array_unique( $ids ) );
  }


  private function countTermsPerTaxonomy( array $taxonomies ): array {
    $slugs = $this->taxonomySlugs( $taxonomies );
    if ( ! $slugs ) {
      return [];
    }

    $join      = $this->translationJoin();
    $condition = $this->needsTranslationCondition();
    $wpdb      = $this->wpdb;

    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT tt.taxonomy, COUNT(*) AS total
       FROM {$wpdb->term_taxonomy} AS tt
       {$join}
       WHERE tt.taxonomy IN (" . implode( ',', array_fill( 0, count( $slugs ), '%s' ) ) . ")
         AND {$condition['sql']}
       GROUP BY tt.taxonomy",
        array_merge( $slugs, $condition['args'] )
      ),
      ARRAY_A
    );

    $counts = [];
    foreach ( (array) $rows as $row ) {
      $counts[ (string) $row['taxonomy'] ] = (int) $row['total'];
    }

    return $counts;
  }


  private function taxonomySlugs( $taxonomies = null ): array {
    if ( $taxonomies === null ) {
      $taxonomies = $this->taxonomiesQuery->getTranslatable();
    }

    if ( ! $taxonomies ) {
      return [];
    }

    return array_values(
      array_map(
        function ( TaxonomyDto $taxonomy ) {
          return $taxonomy->getId();
        },
        $taxonomies
      )
    );
  }


}
