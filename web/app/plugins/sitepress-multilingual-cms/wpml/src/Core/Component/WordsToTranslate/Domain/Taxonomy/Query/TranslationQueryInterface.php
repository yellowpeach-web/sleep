<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Taxonomy\Query;

use WPML\Core\Component\WordsToTranslate\Domain\Taxonomy\TaxonomyTerm;

interface TranslationQueryInterface {


  public function isTranslated( TaxonomyTerm $term, string $lang );


  public function getLastTranslatedOriginalContent( TaxonomyTerm $term, string $lang );


}
