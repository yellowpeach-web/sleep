<?php

namespace WPML\Core\Component\Translation\Domain\Sender;

use WPML\Core\Component\Translation\Domain\Translation;
use WPML\PHP\Exception\Exception;

class SendBatchException extends Exception {

  private $createdTranslations;

  private $sendAnywayElements;


  public function __construct(
    string $message = '',
    array $createdTranslations = [],
    int $code = 0,
    array $sendAnywayElements = []
  ) {
    parent::__construct( $message, $code );

    $this->createdTranslations = $createdTranslations;
    $this->sendAnywayElements  = $sendAnywayElements;
  }


  public function getSendAnywayElements(): array {
    return $this->sendAnywayElements;
  }


  public function getCreatedTranslations(): array {
    return $this->createdTranslations;
  }

}
