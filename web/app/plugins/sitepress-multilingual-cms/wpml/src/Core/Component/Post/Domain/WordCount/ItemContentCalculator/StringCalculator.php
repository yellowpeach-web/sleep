<?php

namespace WPML\Core\Component\Post\Domain\WordCount\ItemContentCalculator;

use WPML\Core\Component\WordsToTranslate\Domain\Calculator\WordsToTranslate;
use WPML\Core\SharedKernel\Component\String\Domain\Repository\RepositoryInterface;
use WPML\PHP\Exception\InvalidArgumentException;
use WPML\PHP\Exception\InvalidItemIdException;
use function WPML\PHP\Logger\error;

class StringCalculator {

  private $wordsToTranslate;

  private $stringRepository;


  public function __construct( WordsToTranslate $wordsToTranslate, RepositoryInterface $stringRepository ) {
    $this->wordsToTranslate = $wordsToTranslate;
    $this->stringRepository = $stringRepository;
  }


  public function calculate( int $itemId ): int {
    $string    = $this->stringRepository->get( $itemId );
    $wordCount = $this->wordsToTranslate->forContent( [ $string->getValue() ], strtolower( $string->getLanguage() ) );

    try {
      $this->stringRepository->updateField( $itemId, 'word_count', $wordCount );
    } catch ( InvalidArgumentException $e ) {
      error( sprintf( 'Failed to update word count for string %d', $itemId ) );
    }

    return $wordCount;
  }


}
