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
      $original_language
      = $this->applySourceLanguageCodeCriteria( $criteria->getSourceLanguageCode() );

      $wpTerms = get_terms(
        array(
          'orderby'    => 'name',
          'taxonomy'   => $criteria->getTaxonomyId(),
          'hide_empty' => true,
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


  private function applySourceLanguageCodeCriteria( string $sourceLanguageCode ) {
      $original_language = $this->sitepress->get_current_language();
    if ( $original_language === $sourceLanguageCode ) {
        return null;
    }
      $this->sitepress->switch_lang( $sourceLanguageCode );

      return $original_language;
  }


}
