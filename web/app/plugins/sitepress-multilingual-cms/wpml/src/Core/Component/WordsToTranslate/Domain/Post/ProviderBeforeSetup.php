<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Post;

use WPML\Core\Component\WordsToTranslate\Domain\Calculator\WordsToTranslate;
use WPML\Core\Component\WordsToTranslate\Domain\Post\Query\JobQueryInterface;
use WPML\Core\Component\WordsToTranslate\Domain\Post\Query\PostQueryInterface;
use WPML\Core\Component\WordsToTranslate\Domain\Post\Query\TranslationQueryInterface;
use WPML\Core\Component\WordsToTranslate\Domain\ProviderInterface;
use WPML\Core\Component\WordsToTranslate\Domain\TranslatableDTO;
use WPML\PHP\Exception\InvalidItemIdException;
use WPML\PHP\Exception\RuntimeException;

class ProviderBeforeSetup implements ProviderInterface{
  const TYPE = 'post';

  private $wordsToTranslate;

  private $postQuery;

  private $jobQuery;

  private $translationQuery;


  public function __construct(
    PostQueryInterface $postQuery,
    WordsToTranslate $wordsToTranslate,
    JobQueryInterface $jobQuery,
    ?TranslationQueryInterface $translationQuery = null
  ) {
    $this->postQuery = $postQuery;
    $this->wordsToTranslate = $wordsToTranslate;
    $this->jobQuery = $jobQuery;
    $this->translationQuery = $translationQuery;
  }


  public function getByIdAndTypeForLangs( $id, $type, $langs, $freshTranslation = false ) {
    if ( $type !== self::TYPE ) {
      return false;
    }

    if ( empty( $langs ) ) {
      return false;
    }

    try {
      $post = $this->postQuery->getById( $id );

      $job = $this->jobQuery->getContentToTranslateForLang( $post, '' );

      $fieldContents = $job->getFieldContents();

      $post->setPreWpmlSetupWordsToTranslate(
        $fieldContents === null
          ? $this->wordsToTranslate->forContent( [ $job->getContent() ], $langs[0] )
          : $this->wordsToTranslate->forFieldContents( $fieldContents, $langs[0] )
      );
      $post->setPreWpmlSetupTermWordsToTranslate( $this->termFieldsWordsPerLang( $post, $langs ) );
    } catch ( \Throwable $e ) {
      return false;
    }

    return $post;
  }


  private function termFieldsWordsPerLang( Post $post, array $langs ): array {
    try {
      $terms = $this->jobQuery->getTerms( $post );
    } catch ( \Throwable $e ) {
      return [];
    }

    if ( ! $terms ) {
      return [];
    }

    $words = [];
    foreach ( $langs as $lang ) {
      $termFields = [];
      foreach ( $terms as $term ) {
        if ( $this->translationQuery && ! $this->translationQuery->isTermTranslatable( $term, $lang ) ) {
          continue;
        }

        foreach ( $term->getContents() as $termContent ) {
          $content = $termContent->getContent();
          if ( is_string( $content ) && $content !== '' ) {
            $termFields[] = $content;
          }
        }
      }

      $words[ $lang ] = $termFields ? $this->wordsToTranslate->forContent( $termFields, $langs[0] ) : 0;
    }

    return $words;
  }


  public function useThisContentForItem( $id, $type, $content ) {
  }


}
