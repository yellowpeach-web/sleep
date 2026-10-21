<?php

namespace WPML\Core\Component\PostHog\Application\Event;

use WPML\Core\Port\Event\Event;

class PostHogTrackingModeResolved extends Event {

  const EVENT_NAME = 'wpml_posthog_tracking_mode_resolved';


  public function __construct( string $trackingMode ) {
    parent::__construct( self::EVENT_NAME, [ $trackingMode ] );
  }

}
