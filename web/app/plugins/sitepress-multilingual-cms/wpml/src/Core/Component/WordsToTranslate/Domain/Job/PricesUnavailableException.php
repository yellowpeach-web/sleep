<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Job;

use WPML\PHP\Exception\RuntimeException;

class PricesUnavailableException extends RuntimeException {

  const MESSAGE = 'The price of automatic translation could not be read.';

}
