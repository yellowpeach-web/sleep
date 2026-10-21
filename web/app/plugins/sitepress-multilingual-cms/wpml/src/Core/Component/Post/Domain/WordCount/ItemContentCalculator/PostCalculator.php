<?php

namespace WPML\Core\Component\Post\Domain\WordCount\ItemContentCalculator;

use WPML\Core\Component\WordsToTranslate\Domain\Calculator\WordsToTranslate;
use WPML\Core\Component\WordsToTranslate\Domain\Post\Query\JobQueryInterface;
use WPML\Core\Component\WordsToTranslate\Domain\Post\Query\PostQueryInterface;
use WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface;
use WPML\PHP\Exception\InvalidItemIdException;

class PostCalculator {


  const ADDITIONAL_CONTENT_FIELD = 'additional';

  private $postQuery;

  private $jobQuery;

  private $wordsToTranslate;


  private $contentFilter;


  private $languages;


  public function __construct(
    PostQueryInterface $postQuery,
    JobQueryInterface $jobQuery,
    WordsToTranslate $wordsToTranslate,
    PostContentFilterInterface $additionalContentFilter,
    LanguagesQueryInterface $languages
  ) {
    $this->postQuery        = $postQuery;
    $this->jobQuery         = $jobQuery;
    $this->wordsToTranslate = $wordsToTranslate;
    $this->contentFilter    = $additionalContentFilter;
    $this->languages        = $languages;
  }


  public function calculate( int $itemId ): int {
    $post = $this->postQuery->getById( $itemId );

    $job = $this->jobQuery->getContentToTranslateForLang( $post, '' );

    $fields = $job->getFieldContents();
    $lang   = $post->getSourceLang() ?: $this->defaultLanguage();

    $additional = $this->contentFilter->getAdditionalContent( '', $itemId ) ?: '';

    if ( $fields === null ) {
      $wordCount = $this->wordsToTranslate->forContent(
        array_filter( [ $job->getContent(), $additional ] ),
        $lang
      );
    } else {
      if ( $additional !== '' ) {
        $fields[ self::ADDITIONAL_CONTENT_FIELD ] = $additional;
      }

      $wordCount = $this->wordsToTranslate->forFieldContents( $fields, $lang );
    }

    return $wordCount;
  }


  private function defaultLanguage(): string {
    try {
      return $this->languages->getDefaultCode();
    } catch ( \Throwable $e ) {
      return '';
    }
  }


}
