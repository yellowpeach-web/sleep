<?php

namespace WPML\Core\Component\Post\Application\WordCount;

use WPML\Core\Component\Post\Domain\WordCount\ItemContentCalculator\PackageCalculator;
use WPML\Core\Component\Post\Domain\WordCount\ItemContentCalculator\StringCalculator;
use WPML\PHP\Exception\InvalidItemIdException;

class ItemWordCountService {

  private $packageCalculator;

  private $stringCalculator;


  public function __construct(
    PackageCalculator $packageCalculator,
    StringCalculator $stringCalculator
  ) {
    $this->packageCalculator = $packageCalculator;
    $this->stringCalculator  = $stringCalculator;
  }


  public function calculatePackage( int $packageId ): int {
    return $this->packageCalculator->calculate( $packageId );
  }


  public function calculateString( int $stringId ): int {
    return $this->stringCalculator->calculate( $stringId );
  }


}
