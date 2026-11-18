<?php

namespace WPML\Legacy\Component\WordsToTranslate\Domain\StringPackage;

use WPML\Core\Component\WordsToTranslate\Domain\StringPackage\Query\BeforeSetupPackagesQueryInterface;
use WPML\Legacy\Component\WordsToTranslate\Domain\StringRegistrationCapture;

class BeforeSetupPackagesQuery implements BeforeSetupPackagesQueryInterface {

  private static $memo = null;


  public function getAll(): array {
    if ( self::$memo !== null ) {
      return self::$memo;
    }

    $captured = StringRegistrationCapture::run(
      function () {
        do_action( 'wpml_tea_estimate_register_string_packages' );

        self::registerBricksComponents();
      }
    );

    $packages = [];

    foreach ( $captured as $string ) {
      $package  = $string['package'];
      $kind     = isset( $package['kind'] ) && is_scalar( $package['kind'] ) ? (string) $package['kind'] : '';
      $kindSlug = isset( $package['kind_slug'] ) && is_scalar( $package['kind_slug'] )
        ? (string) $package['kind_slug']
        : sanitize_title_with_dashes( $kind );
      $name     = isset( $package['name'] ) && is_scalar( $package['name'] ) ? (string) $package['name'] : '';
      $key      = $kindSlug . '|' . $name;

      if ( ! isset( $packages[ $key ] ) ) {
        $packages[ $key ] = [
          'id'       => -( count( $packages ) + 1 ),
          'kind'     => $kind !== '' ? $kind : $kindSlug,
          'kindSlug' => $kindSlug,
          'name'     => $name,
          'strings'  => [],
        ];
      }

      $packages[ $key ]['strings'][] = $string['value'];
    }

    self::$memo = array_values( $packages );

    return self::$memo;
  }


  private static function registerBricksComponents() {
    if ( ! class_exists( '\\Bricks\\Integrations\\Wpml\\Wpml' ) ) {
      return;
    }

    $components = get_option( 'bricks_components', [] );

    if ( ! is_array( $components ) || ! $components ) {
      return;
    }

    $hook      = $GLOBALS['wp_filter']['update_option_bricks_components'] ?? null;
    $callbacks = is_object( $hook ) && isset( $hook->callbacks ) ? (array) $hook->callbacks : [];

    foreach ( $callbacks as $listeners ) {
      foreach ( (array) $listeners as $listener ) {
        $callable = $listener['function'] ?? null;
        $owner    = is_array( $callable ) ? ( $callable[0] ?? null ) : null;
        if (
          ( is_object( $owner ) || is_string( $owner ) )
          && is_a( $owner, \Bricks\Integrations\Wpml\Wpml::class, true )
          && is_callable( $callable )
        ) {
          call_user_func( $callable, [], $components, 'bricks_components' );
        }
      }
    }
  }


  public static function reset() {
    self::$memo = null;
  }


}
