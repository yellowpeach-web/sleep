<?php

namespace WPML\Core\Component\ATE\Application\Query;

use WPML\PHP\Exception\Exception;

class AccountException extends Exception {

  private $reason;


  public function __construct( string $message = '', string $reason = '' ) {
    parent::__construct( $message );
    $this->reason = $reason;
  }


  public function getReason(): string {
    return $this->reason;
  }


}
