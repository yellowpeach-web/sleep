<?php

namespace WPML\Legacy\Component\WordsToTranslate\Domain;

class StringRegistrationCapture {

  private static $captured = null;


  public static function run( callable $register ) {
    self::$captured = [];

    add_action( 'wpml_register_string', [ self::class, 'capture' ], 10, 3 );

    try {
      $register();
    } finally {
      remove_action( 'wpml_register_string', [ self::class, 'capture' ], 10 );

      $captured       = self::$captured;
      self::$captured = null;
    }

    return $captured;
  }


  public static function capture( $value, $name, $package = null ) {
    if ( self::$captured === null || ! is_scalar( $value ) || trim( (string) $value ) === '' ) {
      return;
    }

    $package = is_object( $package ) ? get_object_vars( $package ) : (array) $package;
    $kind    = $package['kind_slug'] ?? $package['kind'] ?? '';
    $pkgName = $package['name'] ?? '';
    $key     = ( is_scalar( $kind ) ? (string) $kind : '' ) . '|'
      . ( is_scalar( $pkgName ) ? (string) $pkgName : '' ) . '|'
      . ( is_scalar( $name ) ? (string) $name : '' );

    self::$captured[ $key ] = [
      'value'   => (string) $value,
      'package' => $package,
    ];
  }


}
