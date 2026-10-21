<?php

namespace WPML\Core\Component\CustomFieldPreferences\Domain;

use function WPML\PHP\Logger\error;

class StorableModes {

  const DONT_TRANSLATE = 0;
  const COPY           = 1;
  const TRANSLATE      = 2;
  const COPY_ONCE      = 3;

  private static $reported = [];


  public static function all(): array {
    return [ self::DONT_TRANSLATE, self::COPY, self::TRANSLATE, self::COPY_ONCE ];
  }


  public static function isValid( int $mode ): bool {
    return in_array( $mode, self::all(), true );
  }


  public static function filter( array $nameToMode ): array {
    $kept = [];
    foreach ( $nameToMode as $name => $mode ) {
      if ( self::isValid( $mode ) ) {
        $kept[ $name ] = $mode;
      }
    }
    return $kept;
  }


  public static function dropped( array $nameToMode, array $kept ): array {
    return array_keys( array_diff_key( $nameToMode, $kept ) );
  }


  public static function quarantine( array $nameToMode, string $type ): array {
    $kept    = self::filter( $nameToMode );
    $dropped = self::dropped( $nameToMode, $kept );

    if ( $dropped && ! isset( self::$reported[ $type ] ) ) {
      self::$reported[ $type ] = true;
      self::report( $type, $dropped );
    }

    return $kept;
  }


  public static function resetReported() {
    self::$reported = [];
  }


  private static function report( string $type, array $names ) {
    $shown = [];
    foreach ( array_slice( $names, 0, 5 ) as $name ) {
      $shown[] = mb_substr( (string) $name, 0, 80, 'UTF-8' );
    }

    error(
      sprintf(
        'WPML meta-settings: ignoring %d stored %s custom field preference(s) whose mode is not one of %s: %s%s',
        count( $names ),
        $type,
        implode( ', ', self::all() ),
        implode( ', ', $shown ),
        count( $names ) > 5 ? ', ...' : ''
      )
    );

    StoreIncidents::record(
      StoreIncidents::MODE_QUARANTINED,
      $type,
      [
        'count' => count( $names ),
        'names' => $shown,
      ]
    );
  }


}
