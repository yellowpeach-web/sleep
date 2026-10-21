<?php

namespace WPML\Core\Component\WordsToTranslate\Application\Service;

use WPML\Core\Component\WordsToTranslate\Domain\Item;
use WPML\Core\Component\WordsToTranslate\Domain\Post\Post;

class WordsForEstimate {


  public static function of( Item $item, $langCode ) {
    return $item instanceof Post
      ? $item->getWordsToTranslateWithoutTerms( $langCode )
      : $item->getWordsToTranslate( $langCode );
  }


}
