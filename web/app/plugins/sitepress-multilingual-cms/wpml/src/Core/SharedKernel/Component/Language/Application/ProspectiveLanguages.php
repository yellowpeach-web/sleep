<?php

namespace WPML\Core\SharedKernel\Component\Language\Application;

class ProspectiveLanguages {

  private $codes = [];


  public function set( array $codes ) {
    $this->codes = array_values( array_unique( $codes ) );
  }


  public function setFromRequest( $value ) {
    if ( ! is_array( $value ) ) {
      $this->codes = [];
      return;
    }

    $codes = [];
    foreach ( $value as $code ) {
      if ( ! is_string( $code ) ) {
        continue;
      }
      $code = strtolower( trim( $code ) );
      if ( preg_match( '/^[a-z0-9_-]{2,20}$/', $code ) === 1 ) {
        $codes[] = $code;
      }
    }

    $this->set( $codes );
  }


  public function get(): array {
    return $this->codes;
  }


  public function isEmpty(): bool {
    return count( $this->codes ) === 0;
  }


}
