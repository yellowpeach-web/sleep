<?php

namespace WPML\Core\Component\Cdt\Domain;

final class CheckinSchedule {

  const INTERVAL_SECONDS = 86400;


  public static function isDue( ?int $lastCheckinAt, int $now ): bool {
    if ( $lastCheckinAt === null ) {
      return true;
    }

    return abs( $now - $lastCheckinAt ) >= self::INTERVAL_SECONDS;
  }


}
