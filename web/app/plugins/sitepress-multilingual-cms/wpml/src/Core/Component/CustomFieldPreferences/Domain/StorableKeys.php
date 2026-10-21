<?php

namespace WPML\Core\Component\CustomFieldPreferences\Domain;

use function WPML\PHP\Logger\error;

class StorableKeys {

  const MAX_CHARS = 255;

  private static $logged = [];


  public static function filter( array $nameToMode ): array {
    $kept = [];
    foreach ( $nameToMode as $name => $mode ) {
      if ( mb_strlen( (string) $name, 'UTF-8' ) > self::MAX_CHARS ) {
        continue;
      }
      $kept[ $name ] = $mode;
    }
    return $kept;
  }


  public static function dropped( array $nameToMode, array $storable ): array {
    return array_keys( array_diff_key( $nameToMode, $storable ) );
  }


  public static function logDropped( string $where, string $type, array $names ) {
    if ( ! $names || isset( self::$logged[ $where . ':' . $type ] ) ) {
      return;
    }
    self::$logged[ $where . ':' . $type ] = true;

    $shown = [];
    foreach ( array_slice( $names, 0, 5 ) as $name ) {
      $shown[] = mb_substr( (string) $name, 0, 80, 'UTF-8' );
    }
    error(
      sprintf(
        'WPML meta-settings %s: skipped %d %s field key(s) that exceed the icl_meta_settings name limit: %s%s',
        $where,
        count( $names ),
        $type,
        implode( ', ', $shown ),
        count( $names ) > 5 ? ', ...' : ''
      )
    );
  }


  public static function resetReported() {
    self::$logged = [];
  }


}
