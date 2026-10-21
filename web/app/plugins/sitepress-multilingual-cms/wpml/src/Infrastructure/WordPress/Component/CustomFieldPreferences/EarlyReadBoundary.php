<?php

namespace WPML\Infrastructure\WordPress\Component\CustomFieldPreferences;

use WPML\Core\Component\CustomFieldPreferences\Application\Service\ReadInjectionService;

class EarlyReadBoundary {

  private static $reader;

  private static $suspended = false;


  public static function suspendDuring( callable $callback ) {
    $previous        = self::$suspended;
    self::$suspended = true;
    try {
      return $callback();
    } finally {
      self::$suspended = $previous;
    }
  }


  public static function filterOption( $value ) {
    if ( self::$suspended ) {
      return $value;
    }
    if ( self::$reader === null ) {
      self::$reader = ContainerFreeServices::reader();
    }

    return self::$reader->injectOption( $value );
  }


}
