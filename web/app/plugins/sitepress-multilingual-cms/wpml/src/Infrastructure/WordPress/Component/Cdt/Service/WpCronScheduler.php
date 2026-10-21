<?php

namespace WPML\Infrastructure\WordPress\Component\Cdt\Service;

use WPML\Core\Component\Cdt\Application\Service\CronSchedulerInterface;

class WpCronScheduler implements CronSchedulerInterface {


  public function scheduleOnceNow( string $hook ): bool {
    return $this->scheduleOnceAt( $hook, time() );
  }


  public function scheduleOnceAt( string $hook, int $at ): bool {
    if ( wp_next_scheduled( $hook ) !== false ) {
      return false;
    }

    return wp_schedule_single_event( $at, $hook ) === true;
  }


}
