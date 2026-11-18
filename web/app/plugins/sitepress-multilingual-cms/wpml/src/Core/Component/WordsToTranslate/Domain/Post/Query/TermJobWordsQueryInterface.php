<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Post\Query;

interface TermJobWordsQueryInterface {


  public function getWordsForTerm( int $termTaxonomyId, ?string $taxonomy ): int;


}
