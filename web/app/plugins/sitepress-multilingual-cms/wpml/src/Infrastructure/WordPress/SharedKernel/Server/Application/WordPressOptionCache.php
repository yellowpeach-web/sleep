<?php

namespace WPML\Infrastructure\WordPress\SharedKernel\Server\Application;

use WPML\Core\SharedKernel\Component\Server\Domain\CacheInterface;

class WordPressOptionCache implements CacheInterface {

  const FIELD_VALUE      = 'value';
  const FIELD_EXPIRES_AT = 'expiresAt';


  public function get( $key ) {
    $stored = get_option( $key, false );

    if (
      ! is_array( $stored )
      || ! array_key_exists( self::FIELD_VALUE, $stored )
      || ! array_key_exists( self::FIELD_EXPIRES_AT, $stored )
      || ! is_int( $stored[ self::FIELD_EXPIRES_AT ] )
    ) {
      return false;
    }

    $expiresAt = $stored[ self::FIELD_EXPIRES_AT ];
    if ( $expiresAt !== 0 && $expiresAt <= time() ) {
      return false;
    }

    return $stored[ self::FIELD_VALUE ];
  }


  public function set( $key, $value, int $expiration = 0 ) {
    return update_option(
      $key,
      [
        self::FIELD_VALUE      => $value,
        self::FIELD_EXPIRES_AT => $expiration > 0 ? time() + $expiration : 0,
      ],
      false
    );
  }


  public function delete( $key ): bool {
    return delete_option( $key );
  }


}
