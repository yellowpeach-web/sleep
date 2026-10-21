<?php

namespace WPML\Core\Component\PostHog\Domain;

class TrackingMode {

  const DISABLED = 'disabled';
  const TEA_ONLY = 'tea_only';
  const ALL      = 'all';

  const API_OFF  = 'off';
  const API_TEA  = 'tea';
  const API_FULL = 'full';


  public static function getAll(): array {
    return [
      self::DISABLED,
      self::TEA_ONLY,
      self::ALL,
    ];
  }


  public static function isValid( string $mode ): bool {
    return in_array( $mode, self::getAll(), true );
  }


  public static function fromApiMode( string $apiMode ): ?string {
    switch ( $apiMode ) {
      case self::API_OFF:
        return self::DISABLED;
      case self::API_TEA:
        return self::TEA_ONLY;
      case self::API_FULL:
        return self::ALL;
      default:
        return null;
    }
  }


  public static function fromBool( bool $shouldRecord ): string {
    return $shouldRecord ? self::ALL : self::DISABLED;
  }


  public static function toBool( string $mode ): bool {
    return $mode === self::TEA_ONLY || $mode === self::ALL;
  }


  public static function isEventAllowed( string $mode, bool $isTeaEvent ): bool {
    if ( $mode === self::ALL ) {
      return true;
    }

    if ( $mode === self::TEA_ONLY ) {
      return $isTeaEvent;
    }

    return false;
  }


}
