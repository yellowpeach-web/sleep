<?php

namespace WPML\Core\Component\Post\Domain\WordCount\ItemContentCalculator;

use WPML\Core\Component\WordsToTranslate\Domain\Calculator\WordsToTranslate;
use WPML\Core\SharedKernel\Component\String\Domain\Repository\RepositoryInterface as StringRepositoryInterface;
use WPML\Core\SharedKernel\Component\StringPackage\Domain\Repository\RepositoryInterface as PackageRepositoryInterface;
use WPML\PHP\Exception\InvalidArgumentException;
use WPML\PHP\Exception\InvalidItemIdException;
use function WPML\PHP\Logger\error;

class PackageCalculator {

  private $stringRepository;

  private $packageRepository;

  private $wordsToTranslate;


  public function __construct(
    StringRepositoryInterface $stringRepository,
    PackageRepositoryInterface $packageRepository,
    WordsToTranslate $wordsToTranslate
  ) {
    $this->stringRepository  = $stringRepository;
    $this->packageRepository = $packageRepository;
    $this->wordsToTranslate  = $wordsToTranslate;
  }


  public function calculate( int $itemId ): int {
    $strings = array_values( $this->stringRepository->getBelongingToPackage( $itemId ) );

    $values = [];
    foreach ( $strings as $string ) {
      $values[] = $string->getValue();
    }

    $wordCount = $strings
      ? $this->wordsToTranslate->forContent( [ implode( ' ', $values ) ], strtolower( $strings[0]->getLanguage() ) )
      : 0;

    try {
      $this->packageRepository->updateField( $itemId, 'word_count', $wordCount );
    } catch ( InvalidArgumentException $e ) {
      error( sprintf( 'Failed to update word count for package %d', $itemId ) );
    }

    return $wordCount;
  }


}
