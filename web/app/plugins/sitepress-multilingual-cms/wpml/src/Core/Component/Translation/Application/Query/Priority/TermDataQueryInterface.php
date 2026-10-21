<?php

namespace WPML\Core\Component\Translation\Application\Query\Priority;

interface TermDataQueryInterface {

  public function getDepthMap( array $termTaxonomyIds ): array;


}
