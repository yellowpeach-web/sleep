<?php

namespace WPML\Infrastructure\WordPress\Component\WordsToTranslate\Domain\Taxonomy;

use WPML\Core\Component\WordsToTranslate\Domain\Taxonomy\Query\TermQueryInterface;
use WPML\Core\Component\WordsToTranslate\Domain\Taxonomy\TaxonomyTerm;
use WPML\PHP\Exception\InvalidItemIdException;
use WPML\TM\Taxonomy\Job\TermWords;
use WPML\TM\Taxonomy\TranslatableTermMeta;

class TaxonomyTermBeforeSetupQuery implements TermQueryInterface {

  private $wpdb;


  public function __construct( $wpdb = null ) {
    $this->wpdb = $wpdb ?: $GLOBALS['wpdb'];
  }


  public function getById( $termTaxonomyId ) {
    $termTaxonomyId = absint( $termTaxonomyId );

    $row = $this->wpdb->get_row(
      "SELECT tt.term_taxonomy_id, tt.taxonomy, tt.description, t.term_id, t.name
       FROM {$this->wpdb->term_taxonomy} AS tt
       JOIN {$this->wpdb->terms} AS t ON t.term_id = tt.term_id
       WHERE tt.term_taxonomy_id = {$termTaxonomyId}",
      ARRAY_A
    );

    if ( ! is_array( $row ) || ! isset( $row['term_taxonomy_id'] ) ) {
      throw new InvalidItemIdException(
        sprintf( 'Term with term_taxonomy_id %s not found.', esc_html( (string) $termTaxonomyId ) )
      );
    }

    $term = new TaxonomyTerm(
      (int) $row['term_taxonomy_id'],
      (int) $row['term_id'],
      (string) $row['taxonomy'],
      $this->defaultLanguage()
    );

    $term->setDateModified( '0000-00-00' );

    $taxonomy = (string) $row['taxonomy'];
    $termId   = (int) $row['term_id'];

    $keys = array_values(
      array_unique(
        array_merge(
          TranslatableTermMeta::keys( $taxonomy ),
          TranslatableTermMeta::resolvedKeysOfTerm( $termId )
        )
      )
    );

    $metaValues = TranslatableTermMeta::valuesOfTermById( $termId, $keys, $taxonomy );
    $content    = (string) $row['name'] . "\n" . (string) $row['description'];
    $metaText   = implode( "\n", $metaValues );
    if ( '' !== $metaText ) {
      $content .= "\n" . $metaText;
    }

    $term->setContent( $content );
    $term->setFieldContents(
      TermWords::fieldsOf( (string) $row['name'], (string) $row['description'], $metaValues, (int) $row['term_taxonomy_id'] )
    );

    return $term;
  }


  private function defaultLanguage(): string {
    $sitepress = $GLOBALS['sitepress'] ?? null;

    return is_object( $sitepress ) && method_exists( $sitepress, 'get_default_language' )
      ? (string) $sitepress->get_default_language()
      : 'en';
  }


}
