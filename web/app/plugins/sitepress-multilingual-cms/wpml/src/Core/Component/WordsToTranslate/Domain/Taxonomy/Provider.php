<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Taxonomy;

use WPML\Core\Component\WordsToTranslate\Domain\Calculator\WordsToTranslate;
use WPML\Core\Component\WordsToTranslate\Domain\LastTranslationFactory;
use WPML\Core\Component\WordsToTranslate\Domain\ProviderInterface;
use WPML\Core\Component\WordsToTranslate\Domain\Taxonomy\Query\TermQueryInterface;
use WPML\Core\Component\WordsToTranslate\Domain\Taxonomy\Query\TranslationQueryInterface;
use WPML\Core\Component\WordsToTranslate\Domain\TranslatableDTO;
use WPML\PHP\Exception\InvalidItemIdException;

class Provider implements ProviderInterface {
  const TYPE = 'taxonomy';

  private $termQuery;

  private $translationQuery;

  private $lastTranslationFactory;

  private $wordsToTranslate;


  public function __construct(
    TermQueryInterface $termQuery,
    TranslationQueryInterface $translationQuery,
    LastTranslationFactory $lastTranslationFactory,
    WordsToTranslate $wordsToTranslate
  ) {
    $this->termQuery              = $termQuery;
    $this->translationQuery       = $translationQuery;
    $this->lastTranslationFactory = $lastTranslationFactory;
    $this->wordsToTranslate       = $wordsToTranslate;
  }


  public function getByIdAndTypeForLangs( $id, $type, $langs, $freshTranslation = false ) {
    if ( $type !== self::TYPE ) {
      return false;
    }

    $term = $this->termQuery->getById( $id );

    foreach ( $langs as $lang ) {
      $lastTranslation = $this->lastTranslationFactory->createForItem( $term, $lang );

      if (
        $lang === $term->getSourceLang()
        || ( ! $freshTranslation && $this->translationQuery->isTranslated( $term, $lang ) )
      ) {
        $lastTranslation->setOriginalContent( $term->getContent() ?: '' );
        $lastTranslation->setWordsToTranslate( 0 );
        $term->addLastTranslation( $lastTranslation );
        continue;
      }

      $lastTranslation->setOriginalContent(
        $freshTranslation
          ? ''
          : $this->translationQuery->getLastTranslatedOriginalContent( $term, $lang )
      );

      $this->wordsToTranslate->forLastTranslation( $lastTranslation, $term );
      $term->addLastTranslation( $lastTranslation );
    }

    return $term;
  }


  public function useThisContentForItem( $id, $type, $content ) {
  }


}
