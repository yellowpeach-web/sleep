<?php

namespace WPML\Core\Component\ATE\Application\Service;

use WPML\Core\Component\ATE\Domain\SiteRegistration;
use WPML\PHP\Exception\Exception;

class EngineServiceException extends Exception {

  private $errorCode;


  public function __construct( string $message = '', string $errorCode = '', ?\Throwable $previous = null ) {
    parent::__construct( $message, 0, $previous );
    $this->errorCode = $errorCode;
  }


  public static function notRegistered(): self {
    return new self( SiteRegistration::NOT_REGISTERED_MESSAGE, SiteRegistration::NOT_REGISTERED_ERROR_CODE );
  }


  public function getErrorCode(): string {
    return $this->errorCode;
  }


}
