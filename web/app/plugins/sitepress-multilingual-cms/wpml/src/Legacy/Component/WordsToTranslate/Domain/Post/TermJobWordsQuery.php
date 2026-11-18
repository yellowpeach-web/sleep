<?php

namespace WPML\Legacy\Component\WordsToTranslate\Domain\Post;

use WPML\Core\Component\WordsToTranslate\Domain\Post\Query\TermJobWordsQueryInterface;
use WPML\TM\Taxonomy\Job\BuildsTaxonomyTermJobModel;

class TermJobWordsQuery implements TermJobWordsQueryInterface {

  use BuildsTaxonomyTermJobModel;

  private $wordsByTermTaxonomyId = [];


  public function getWordsForTerm( int $termTaxonomyId, ?string $taxonomy ): int {
    if ( ! isset( $this->wordsByTermTaxonomyId[ $termTaxonomyId ] ) ) {
      $term = self::withoutTermAdjustId(
        function () use ( $termTaxonomyId, $taxonomy ) {
          return get_term_by( 'term_taxonomy_id', $termTaxonomyId, (string) $taxonomy );
        }
      );

      $this->wordsByTermTaxonomyId[ $termTaxonomyId ] =
        $term instanceof \WP_Term && $term->term_taxonomy_id === $termTaxonomyId
          ? $this->countTaxonomyTermWords( $term )
          : 0;
    }

    return $this->wordsByTermTaxonomyId[ $termTaxonomyId ];
  }


}
