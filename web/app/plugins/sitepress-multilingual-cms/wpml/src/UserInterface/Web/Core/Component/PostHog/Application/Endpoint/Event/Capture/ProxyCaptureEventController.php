<?php

namespace WPML\UserInterface\Web\Core\Component\PostHog\Application\Endpoint\Event\Capture;

use WPML\Core\Component\PostHog\Application\Service\Config\ConfigService;
use WPML\Core\Component\PostHog\Application\Service\Event\CaptureEventService;
use WPML\Core\Component\PostHog\Application\Service\Event\EventInstanceService;
use WPML\Core\Component\PostHog\Application\Repository\PostHogStateRepositoryInterface;
use WPML\Core\Component\PostHog\Domain\Repository\SetupWizardUUIDRepositoryInterface;
use WPML\Core\Component\PostHog\Domain\TrackingMode;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\PHP\Exception\RemoteException;

class ProxyCaptureEventController implements EndpointInterface {

  private $configService;

  private $eventInstanceService;

  private $captureEventService;

  private $wizardUUIDRepository;

  private $postHogStateRepository;

  const UI_LOCATION_SETUP_WIZARD = 'setup_wizard';

  const SERVER_OWNED_PROPERTIES = [ 'queued_event', 'original_event_time', 'replay_time', 'queue_source' ];


  public function __construct(
    ConfigService $configService,
    EventInstanceService $eventInstanceService,
    CaptureEventService $captureEventService,
    SetupWizardUUIDRepositoryInterface $wizardUUIDRepository,
    PostHogStateRepositoryInterface $postHogStateRepository
  ) {
    $this->configService          = $configService;
    $this->eventInstanceService   = $eventInstanceService;
    $this->captureEventService    = $captureEventService;
    $this->wizardUUIDRepository   = $wizardUUIDRepository;
    $this->postHogStateRepository = $postHogStateRepository;
  }


  public function handle( $requestData = null ): array {
    $normalized = $this->normalizeRequestData( $requestData );

    if (
      ! is_array( $normalized ) ||
      ! array_key_exists( 'distinctId', $normalized ) ||
      ! array_key_exists( 'eventName', $normalized ) ||
      ! array_key_exists( 'eventData', $normalized ) ||
      ! is_string( $normalized['distinctId'] ) ||
      ! is_string( $normalized['eventName'] ) ||
      ! is_array( $normalized['eventData'] )
    ) {
      return [
        'success' => false,
        'message' => 'Invalid request data'
      ];
    }

    try {
      $config     = $this->configService->create();
      $isTEAEvent = isset( $normalized['isTEAEvent'] ) && $normalized['isTEAEvent'];
      $normalized['eventData'] = array_diff_key(
        $normalized['eventData'],
        array_flip( self::SERVER_OWNED_PROPERTIES )
      );

      if ( $isTEAEvent ) {
        $event = $this->eventInstanceService->getCustomTEAEvent(
          $normalized['eventName'],
          $normalized['eventData']
        );
      } else {
        $event = $this->eventInstanceService->getCustomEvent(
          $normalized['eventName'],
          $normalized['eventData']
        );
      }

      $event->addProperties( $this->serverSideProperties( $normalized ) );

      $result = $this->captureEventService->capture(
        $config,
        $event
      );

      if ( ! $result ) {
        return [
          'success' => false,
          'message' => 'Failed to capture event, make sure PostHog is enabled and distinct_id is set.',
        ];
      }
    } catch ( RemoteException $e ) {
      return [
        'success' => false,
        'message' => $e->getMessage(),
      ];
    }

    return [
      'success' => true,
      'message' => '',
    ];
  }


  private function serverSideProperties( array $normalized ): array {
    $properties = [ 'distinct_id' => $normalized['distinctId'] ];

    $eventData  = $normalized['eventData'];
    $uiLocation = is_array( $eventData ) && isset( $eventData['ui_location'] ) ? $eventData['ui_location'] : null;
    if (
      self::UI_LOCATION_SETUP_WIZARD === $uiLocation
      && TrackingMode::ALL === $this->postHogStateRepository->getTrackingMode()
    ) {
      $wizardUUID = $this->wizardUUIDRepository->get();
      if ( is_string( $wizardUUID ) && '' !== $wizardUUID ) {
        $properties['wizard_uuid'] = $wizardUUID;
      }
    }

    return $properties;
  }


  private function normalizeRequestData( $requestData ) {
    if ( ! is_array( $requestData ) ) {
      return null;
    }

    $normalized = [];

    if ( array_key_exists( 'distinctId', $requestData ) ) {
      $normalized['distinctId'] = $requestData['distinctId'];
    } elseif ( array_key_exists( 'distinct_id', $requestData ) ) {
      $normalized['distinctId'] = $requestData['distinct_id'];
    }

    if ( array_key_exists( 'eventName', $requestData ) ) {
      $normalized['eventName'] = $requestData['eventName'];
    } elseif ( array_key_exists( 'event_name', $requestData ) ) {
      $normalized['eventName'] = $requestData['event_name'];
    }

    if ( array_key_exists( 'eventData', $requestData ) ) {
      $normalized['eventData'] = $requestData['eventData'];
    } elseif ( array_key_exists( 'event_data', $requestData ) ) {
      $normalized['eventData'] = $requestData['event_data'];
    }

    if ( array_key_exists( 'isTEAEvent', $requestData ) ) {
      $normalized['isTEAEvent'] = $requestData['isTEAEvent'];
    } elseif ( array_key_exists( 'is_tea_event', $requestData ) ) {
      $normalized['isTEAEvent'] = $requestData['is_tea_event'];
    }

    return $normalized;
  }


}
