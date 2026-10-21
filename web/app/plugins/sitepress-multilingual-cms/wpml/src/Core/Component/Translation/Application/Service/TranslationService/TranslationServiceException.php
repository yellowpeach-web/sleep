<?php

namespace WPML\Core\Component\Translation\Application\Service\TranslationService;

use WPML\Core\Component\Translation\Application\Service\TranslationService\Dto\ResultDto;
use WPML\PHP\Exception\Exception;

class TranslationServiceException extends Exception {

  private $partialResult;


  public function __construct(
    string $message = '',
    ?ResultDto $partialResult = null,
    int $code = 0
  ) {
    parent::__construct( $message, $code );

    $this->partialResult = $partialResult;
  }


  public function getPartialResult() {
    return $this->partialResult;
  }

}
