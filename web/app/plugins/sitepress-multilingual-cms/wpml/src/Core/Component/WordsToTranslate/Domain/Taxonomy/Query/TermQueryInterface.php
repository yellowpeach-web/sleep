<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Taxonomy\Query;

use WPML\Core\Component\WordsToTranslate\Domain\Taxonomy\TaxonomyTerm;
use WPML\PHP\Exception\InvalidItemIdException;

interface TermQueryInterface {


  public function getById( $termTaxonomyId );


}
