<?php

namespace WPML\Legacy\Component\WordsToTranslate\Domain\Taxonomy;

use WPML\Core\Component\WordsToTranslate\Domain\Taxonomy\Query\TranslationQueryInterface;
use WPML\Core\Component\WordsToTranslate\Domain\Taxonomy\TaxonomyTerm;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;
use WPML\TM\Dashboard\Taxonomy\TaxonomyDashboardData;

class TranslationQuery implements TranslationQueryInterface {

  private $states = [];


  public function isTranslated( TaxonomyTerm $term, string $lang ) {
    $state = $this->getState( $term, $lang );

    if ( ! $state || $state['needs_update'] ) {
      return false;
    }

    if ( $state['status'] === null ) {
      return true;
    }

    return ! in_array(
      $state['status'],
      [ TranslationStatus::NOT_TRANSLATED, TranslationStatus::ATE_CANCELED ],
      true
    );
  }


  public function getLastTranslatedOriginalContent( TaxonomyTerm $term, string $lang ) {
    $state = $this->getState( $term, $lang );

    if ( ! $state || ! $state['needs_update'] ) {
      return '';
    }

    $snapshot = get_term_meta(
      $term->getTermId(),
      TaxonomyDashboardData::WC_SNAPSHOT_META_PREFIX . $lang,
      true
    );

    return is_string( $snapshot ) ? $snapshot : '';
  }


  private function getState( TaxonomyTerm $term, string $lang ) {
    $termTaxonomyId = $term->getId();

    if ( ! isset( $this->states[ $termTaxonomyId ] ) ) {
      $this->states[ $termTaxonomyId ] = $this->loadStates( $termTaxonomyId, $term->getType() );
    }

    return $this->states[ $termTaxonomyId ][ $lang ] ?? null;
  }


  private function loadStates( $termTaxonomyId, $elementType ) {
    $wpdb = $GLOBALS['wpdb'];

    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT
          translations.language_code,
          translation_status.status,
          translation_status.needs_update
        FROM {$wpdb->prefix}icl_translations original
        INNER JOIN {$wpdb->prefix}icl_translations translations
          ON translations.trid = original.trid
        LEFT JOIN {$wpdb->prefix}icl_translation_status translation_status
          ON translation_status.translation_id = translations.translation_id
        WHERE original.element_id = %d
          AND original.element_type = %s",
        $termTaxonomyId,
        $elementType
      )
    );

    $states = [];
    foreach ( is_array( $rows ) ? $rows : [] as $row ) {
      $states[ (string) $row->language_code ] = [
        'status'       => $row->status === null ? null : (int) $row->status,
        'needs_update' => (int) $row->needs_update,
      ];
    }

    return $states;
  }


}
