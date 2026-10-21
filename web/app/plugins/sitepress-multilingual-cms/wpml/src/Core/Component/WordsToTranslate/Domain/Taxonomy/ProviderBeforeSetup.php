<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Taxonomy;

use WPML\Core\Component\WordsToTranslate\Domain\Taxonomy\Query\TermQueryInterface;
use WPML\Core\Component\WordsToTranslate\Domain\Calculator\WordsToTranslate;
use WPML\Core\Component\WordsToTranslate\Domain\ProviderInterface;
use WPML\Core\Component\WordsToTranslate\Domain\TranslatableDTO;

class ProviderBeforeSetup implements ProviderInterface {

  const TYPE = 'taxonomy';

  private $termQuery;

  private $wordsToTranslate;


  public function __construct( TermQueryInterface $termQuery, WordsToTranslate $wordsToTranslate ) {
    $this->termQuery       = $termQuery;
    $this->wordsToTranslate = $wordsToTranslate;
  }


  public function getByIdAndTypeForLangs( $id, $type, $langs, $freshTranslation = false ) {
    if ( $type !== self::TYPE ) {
      return false;
    }

    if ( empty( $langs ) ) {
      return false;
    }

    try {
      $term = $this->termQuery->getById( $id );
    } catch ( \Throwable $e ) {
      return false;
    }

    $term->setPreWpmlSetupWordsToTranslate(
      $this->wordsToTranslate->forContent( [ (string) $term->getContent() ], $langs[0] )
    );

    return $term;
  }


  public function useThisContentForItem( $id, $type, $content ) {
  }


}
