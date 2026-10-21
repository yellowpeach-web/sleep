<?php

namespace WPML\Legacy\Component\WordsToTranslate\Domain\Taxonomy;

use WPML\Core\Component\WordsToTranslate\Domain\Taxonomy\Query\TermQueryInterface;
use WPML\Core\Component\WordsToTranslate\Domain\Taxonomy\TaxonomyTerm;
use WPML\PHP\Exception\InvalidItemIdException;
use WPML\TM\Taxonomy\TranslatableTermMeta;

class TermQuery implements TermQueryInterface {


  public function getById( $termTaxonomyId ) {
    $wpdb = $GLOBALS['wpdb'];

    $row = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT
          tt.term_taxonomy_id,
          tt.term_id,
          tt.taxonomy,
          t.name,
          tt.description,
          translations.language_code,
          translations.source_language_code
        FROM {$wpdb->prefix}term_taxonomy tt
        INNER JOIN {$wpdb->prefix}terms t
          ON t.term_id = tt.term_id
        LEFT JOIN {$wpdb->prefix}icl_translations translations
          ON translations.element_id = tt.term_taxonomy_id
          AND translations.element_type = CONCAT( %s, tt.taxonomy )
        WHERE tt.term_taxonomy_id = %d",
        TaxonomyTerm::TYPE_PREFIX,
        $termTaxonomyId
      )
    );

    if ( ! $row ) {
      throw new InvalidItemIdException( sprintf( 'Term %d not found', $termTaxonomyId ) );
    }

    if ( $row->language_code === null ) {
      throw new InvalidItemIdException(
        sprintf( 'No language found for term %d', $termTaxonomyId )
      );
    }

    $taxonomy = (string) $row->taxonomy;

    $term = new TaxonomyTerm(
      (int) $row->term_taxonomy_id,
      (int) $row->term_id,
      $taxonomy,
      (string) ( $row->source_language_code ?: $row->language_code )
    );

    $content  = (string) $row->name . "\n" . (string) $row->description;
    $metaText = TranslatableTermMeta::metaTextById(
      (int) $row->term_id,
      TranslatableTermMeta::keys( $taxonomy ),
      $taxonomy
    );
    if ( '' !== $metaText ) {
      $content .= "\n" . $metaText;
    }

    $term->setContent( $content );

    return $term;
  }


}
