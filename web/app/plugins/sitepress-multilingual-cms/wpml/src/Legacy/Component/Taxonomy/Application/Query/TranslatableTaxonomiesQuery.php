<?php

namespace WPML\Legacy\Component\Taxonomy\Application\Query;

use WPML\Core\SharedKernel\Component\Taxonomy\Application\Query\Dto\TaxonomyDto;
use WPML\Core\SharedKernel\Component\Taxonomy\Application\Query\TranslatableTaxonomiesQueryInterface;
use WPML\TM\ATE\TranslateEverything\TranslatableTaxonomies;

class TranslatableTaxonomiesQuery implements TranslatableTaxonomiesQueryInterface {


  public function getTranslatable(): array {
    $taxonomies = [];

    foreach ( TranslatableTaxonomies::getEligibleForTea() as $slug ) {
      $taxonomy = get_taxonomy( $slug );
      if ( ! $taxonomy ) {
        continue;
      }

      $plural   = $this->label( $taxonomy->labels, 'name', $slug );
      $singular = $this->label( $taxonomy->labels, 'singular_name', $plural );

      $taxonomies[] = new TaxonomyDto( $slug, $singular, $plural );
    }

    return $taxonomies;
  }


  private function label( $labels, string $key, string $fallback ): string {
    $label = $labels->{$key} ?? null;

    return is_string( $label ) && $label !== '' ? $label : $fallback;
  }


}
