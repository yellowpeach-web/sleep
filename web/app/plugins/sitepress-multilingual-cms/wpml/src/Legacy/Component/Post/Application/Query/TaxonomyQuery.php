<?php

namespace WPML\Legacy\Component\Post\Application\Query;

use WPML\Core\Component\Post\Application\Query\Criteria\TaxonomyCriteria;
use WPML\Core\Component\Post\Application\Query\Criteria\TaxonomyTermCriteria;
use WPML\Core\Component\Post\Application\Query\Dto\PostTaxonomyDto;
use WPML\Core\Component\Post\Application\Query\Dto\PostTermDto;
use WPML\Core\Component\Post\Application\Query\TaxonomyQueryInterface;
use WPML\PHP\Exception\InvalidArgumentException;

class TaxonomyQuery implements TaxonomyQueryInterface
{

    private $sitepress;


  public function __construct( \SitePress $sitepress ) {
      $this->sitepress = $sitepress;
  }


  public function getTaxonomies( TaxonomyCriteria $criteria ): array {
      $original_language
      = $this->applySourceLanguageCodeCriteria( $criteria->getSourceLanguageCode() );

      $wpTaxonomies = get_taxonomies( [], 'objects' );

    if ( $original_language ) {
        $this->sitepress->switch_lang();
    }

      return array_map(
        function ( $wpTaxonomy ) {
            return new PostTaxonomyDto(
              $wpTaxonomy->name,
              $wpTaxonomy->label,
              array_values( $wpTaxonomy->object_type )
            );
        },
        $wpTaxonomies
      );
  }


  public function getTerms( TaxonomyTermCriteria $criteria ): array {
    if ( $criteria->getOriginalsOnly() ) {
        return $this->getOriginalTerms( $criteria );
    }

      $original_language
      = $this->applySourceLanguageCodeCriteria( $criteria->getSourceLanguageCode() );

      $wpTerms = get_terms(
        array(
          'orderby'    => $criteria->getOrderBy(),
          'taxonomy'   => $criteria->getTaxonomyId(),
          'hide_empty' => $criteria->getHideEmpty(),
          'search'     => $criteria->getSearch() ?? '',
          'number'     => $criteria->getLimit() ?? '',
          'offset'     => (int) $criteria->getOffset(),
          )
      );

    if ( $original_language ) {
        $this->sitepress->switch_lang();
    }

    if ( is_wp_error( $wpTerms ) ) {
      if ( 'invalid_taxonomy' === $wpTerms->get_error_code() ) {
          throw new InvalidArgumentException(
            'taxonomyId names a taxonomy that is not registered: "'
            . esc_html( $criteria->getTaxonomyId() ) . '".'
          );
      }

        throw new \Exception( 'Could not retrieve terms.' );
    }

      return array_values(
        array_map(
          function ( $wpTerm ) {
              return new PostTermDto( $wpTerm->term_id, $wpTerm->name );
          },
          $wpTerms
        )
      );
  }

  private function getOriginalTerms( TaxonomyTermCriteria $criteria ): array {
      global $wpdb;

    if ( ! taxonomy_exists( $criteria->getTaxonomyId() ) ) {
        throw new InvalidArgumentException( 'Unknown taxonomy: ' . esc_html( $criteria->getTaxonomyId() ) );
    }

      $where = [ 'original.source_language_code IS NULL' ];
    if ( $criteria->getHideEmpty() ) {
        $where[] = 'tt.count > 0';
    }
    if ( null !== $criteria->getParentTtid() ) {
        $where[] = 'tt.parent = ' . $criteria->getParentTtid();
    }
    if ( $criteria->getIncludeTtids() ) {
        $ids     = array_values( array_unique( $criteria->getIncludeTtids() ) );
        $where[] = 'tt.term_taxonomy_id IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')';
    }
      $order    = 'name' === $criteria->getOrderBy() ? 't.name, tt.term_taxonomy_id' : 'tt.term_taxonomy_id';
      $limitSql = '';
    if ( null !== $criteria->getLimit() ) {
        $limitSql = ' LIMIT ' . $criteria->getLimit() . ' OFFSET ' . max( 0, (int) $criteria->getOffset() );
    }

    if ( null !== $criteria->getSearch() && '' !== $criteria->getSearch() ) {
        $rows = $wpdb->get_results(
          "SELECT tt.term_taxonomy_id, tt.term_id, tt.parent, tt.description, t.name
          FROM {$wpdb->prefix}icl_translations original
          INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = original.element_id
          INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
          WHERE original.element_type = '" . esc_sql( 'tax_' . $criteria->getTaxonomyId() ) . "'
          AND " . esc_sql( implode( ' AND ', $where ) ) . " AND " . $wpdb->prepare( "t.name LIKE %s ESCAPE '|'", \WPML\Utils\LikeEscape::contains( $criteria->getSearch() ) ) . "
          ORDER BY " . esc_sql( $order ) . esc_sql( $limitSql ),
          ARRAY_A
        );
    } else {
        $rows = $wpdb->get_results(
          "SELECT tt.term_taxonomy_id, tt.term_id, tt.parent, tt.description, t.name
          FROM {$wpdb->prefix}icl_translations original
          INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = original.element_id
          INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
          WHERE original.element_type = '" . esc_sql( 'tax_' . $criteria->getTaxonomyId() ) . "'
          AND " . esc_sql( implode( ' AND ', $where ) ) . ' ORDER BY ' . esc_sql( $order ) . esc_sql( $limitSql ),
          ARRAY_A
        );
    }
    if ( ! is_array( $rows ) ) {
        throw new \Exception( 'Could not retrieve terms.' );
    }

      return array_map(
        function ( array $row ): PostTermDto {
            return new PostTermDto(
              (int) $row['term_id'],
              (string) $row['name'],
              (int) $row['term_taxonomy_id'],
              (int) $row['parent'],
              (string) $row['description']
            );
        },
        $rows
      );
  }


  private function applySourceLanguageCodeCriteria( string $sourceLanguageCode ) {
      $original_language = $this->sitepress->get_current_language();
    if ( $original_language === $sourceLanguageCode ) {
        return null;
    }
      $this->sitepress->switch_lang( $sourceLanguageCode );

      return $original_language;
  }


}
