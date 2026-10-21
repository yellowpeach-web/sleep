<?php

namespace WPML\Infrastructure\WordPress\Component\CustomFieldPreferences;

use WPML\Core\Component\CustomFieldPreferences\Application\Service\WriteBoundaryService;

class EarlyWriteBoundary {

  private static $service;


  public static function filterValue( $value, $old = null ) {
    if ( self::$service === null && ! class_exists( WriteBoundaryService::class ) ) {
      return $value;
    }
    if ( self::$service === null ) {
      self::$service = ContainerFreeServices::writeBoundary();
    }

    return self::$service->filter( $value, $old );
  }


}
