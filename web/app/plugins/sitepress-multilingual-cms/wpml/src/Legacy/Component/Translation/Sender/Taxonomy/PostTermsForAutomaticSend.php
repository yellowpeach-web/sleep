<?php

namespace WPML\Legacy\Component\Translation\Sender\Taxonomy;

use WPML\Core\Component\Translation\Application\Service\Taxonomy\PostTermsForAutomaticSendInterface;

class PostTermsForAutomaticSend implements PostTermsForAutomaticSendInterface {

  private $wpdb;

  private $coveredInLanguage;

  private $translatableTaxonomies;


  public function __construct( $wpdb = null, ?callable $coveredInLanguage = null, ?callable $translatableTaxonomies = null ) {
    $this->wpdb                   = $wpdb;
    $this->coveredInLanguage      = $coveredInLanguage;
    $this->translatableTaxonomies = $translatableTaxonomies;
  }


  public function uncoveredTermsOfPosts( array $postIds, array $targetLanguages ): array {
    $postIds         = array_values( array_unique( array_filter( array_map( 'intval', $postIds ) ) ) );
    $targetLanguages = array_values( array_unique( array_filter( array_map( 'strval', $targetLanguages ) ) ) );
    if ( ! $postIds || ! $targetLanguages ) {
      return [];
    }

    $wpdb        = $this->wpdb();
    $postsByType = [];
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT ID, post_type FROM {$wpdb->posts} WHERE ID IN ( " . implode( ', ', array_fill( 0, count( $postIds ), '%d' ) ) . ' )',
        $postIds
      ),
      ARRAY_A
    );
    foreach ( is_array( $rows ) ? $rows : [] as $row ) {
      $postsByType[ (string) ( $row['post_type'] ?? '' ) ][] = (int) ( $row['ID'] ?? 0 );
    }

    $termIds = [];
    foreach ( $postsByType as $postType => $typePostIds ) {
      $taxonomies = array_values( array_filter( array_map( 'strval', call_user_func( $this->translatableTaxonomies(), $postType ) ) ) );
      if ( ! $taxonomies ) {
        continue;
      }

      $termIds = array_merge(
        $termIds,
        array_map(
          'intval',
          $wpdb->get_col(
            $wpdb->prepare(
              "SELECT DISTINCT tr.term_taxonomy_id
              FROM {$wpdb->term_relationships} tr
              JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
              WHERE tr.object_id IN ( " . implode( ', ', array_fill( 0, count( $typePostIds ), '%d' ) ) . ' )
                AND tt.taxonomy IN ( ' . implode( ', ', array_fill( 0, count( $taxonomies ), '%s' ) ) . ' )',
              array_merge( $typePostIds, $taxonomies )
            )
          )
        )
      );
    }
    $termIds = array_values( array_unique( array_filter( $termIds ) ) );
    if ( ! $termIds ) {
      return [];
    }

    $byLanguage = [];
    foreach ( $targetLanguages as $lang ) {
      $covered   = array_map( 'intval', call_user_func( $this->coveredInLanguage(), $termIds, $lang ) );
      $uncovered = array_values( array_diff( $termIds, $covered ) );
      if ( $uncovered ) {
        $byLanguage[ $lang ] = $uncovered;
      }
    }

    return $byLanguage;
  }


  private function wpdb(): \wpdb {
    if ( null === $this->wpdb ) {
      $this->wpdb = $GLOBALS['wpdb'];
    }

    return $this->wpdb;
  }


  private function coveredInLanguage(): callable {
    if ( null === $this->coveredInLanguage ) {
      $this->coveredInLanguage = function ( array $termTaxonomyIds, string $lang ): array {
        return \WPML_Element_Translation_Package::termsCoveredInLanguage( $termTaxonomyIds, $lang );
      };
    }

    return $this->coveredInLanguage;
  }


  private function translatableTaxonomies(): callable {
    if ( null === $this->translatableTaxonomies ) {
      $this->translatableTaxonomies = function ( string $postType ): array {
        return (array) $GLOBALS['sitepress']->get_translatable_taxonomies( false, $postType );
      };
    }

    return $this->translatableTaxonomies;
  }

}
