<?php

namespace WPML\Core\SharedKernel\Component\Taxonomy\Application\Query;

use WPML\Core\SharedKernel\Component\Taxonomy\Application\Query\Dto\TaxonomyDto;

interface TranslatableTaxonomiesQueryInterface {


  public function getTranslatable(): array;


}
