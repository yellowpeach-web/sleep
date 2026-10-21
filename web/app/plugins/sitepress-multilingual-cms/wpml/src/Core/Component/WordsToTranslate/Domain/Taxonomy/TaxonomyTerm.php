<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Taxonomy;

use WPML\Core\Component\WordsToTranslate\Domain\Item;

class TaxonomyTerm extends Item {

  const TYPE_PREFIX = 'tax_';

  private $termId;

  private $taxonomy;

  private $preWpmlSetupWordsToTranslate;


  public function __construct(
    int $termTaxonomyId,
    int $termId,
    string $taxonomy,
    string $sourceLang
  ) {
    parent::__construct( $termTaxonomyId, self::TYPE_PREFIX . $taxonomy, $sourceLang );

    $this->termId   = $termId;
    $this->taxonomy = $taxonomy;
  }


  public function getTermId() {
    return $this->termId;
  }


  public function getTaxonomy() {
    return $this->taxonomy;
  }


  public function setPreWpmlSetupWordsToTranslate( int $words ): void {
    $this->preWpmlSetupWordsToTranslate = $words;
  }


  public function getContentCount() {
    if ( $this->preWpmlSetupWordsToTranslate !== null ) {
      return $this->preWpmlSetupWordsToTranslate;
    }

    return parent::getContentCount();
  }


  public function getWordsToTranslate( $langCode = null ) {
    if ( $this->preWpmlSetupWordsToTranslate !== null ) {
      return $this->preWpmlSetupWordsToTranslate;
    }

    return parent::getWordsToTranslate( $langCode );
  }


}
