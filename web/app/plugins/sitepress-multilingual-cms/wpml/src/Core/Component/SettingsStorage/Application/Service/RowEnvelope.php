<?php

namespace WPML\Core\Component\SettingsStorage\Application\Service;

class RowEnvelope {

  const FIELD = 'v';


  public function wrap( $value ): array {
    return [ self::FIELD => $value ];
  }


  public function isValidRow( $row ): bool {
    return is_array( $row ) && array_key_exists( self::FIELD, $row );
  }


  public function value( array $row ) {
    return $row[ self::FIELD ];
  }


}
