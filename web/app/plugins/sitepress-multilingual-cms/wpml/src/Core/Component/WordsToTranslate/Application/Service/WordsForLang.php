<?php

namespace WPML\Core\Component\WordsToTranslate\Application\Service;

use WPML\Core\Component\WordsToTranslate\Domain\Item;
use WPML\Core\Component\WordsToTranslate\Domain\Post\Post;
use WPML\Core\Component\WordsToTranslate\Domain\Post\Query\TermJobWordsQueryInterface;

class WordsForLang {

  private $termJobWordsQuery;


  public function __construct( TermJobWordsQueryInterface $termJobWordsQuery ) {
    $this->termJobWordsQuery = $termJobWordsQuery;
  }


  public function of( Item $item, string $lang ): array {
    $words = [ 'wordsToTranslate' => $item->getWordsToTranslate( $lang ) ];
    if ( ! $item instanceof Post ) {
      return $words;
    }

    $words['wordsToTranslateWithoutTerms'] = $item->getWordsToTranslateWithoutTerms( $lang );
    $words['terms']                        = [];
    foreach ( $item->getTerms() ?: [] as $term ) {
      $termWords = 0;
      foreach ( $term->getContents() as $content ) {
        $termWords += $content->getWordsToTranslate( $lang );
      }
      if ( $termWords > 0 ) {
        $termWords = $this->termJobWordsQuery->getWordsForTerm( $term->getId(), $term->getTaxonomy() );
      }
      if ( $termWords > 0 ) {
        $words['terms'][ $term->getId() ] = [
          'taxonomy'         => (string) $term->getTaxonomy(),
          'wordsToTranslate' => $termWords,
        ];
      }
    }

    return $words;
  }


}
