<?php

namespace WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application;

use WPML\Core\Component\PostHog\Application\Service\Config\ConfigService;
use WPML\Core\Component\PostHog\Application\Service\Event\CaptureEventService;
use WPML\Core\Component\PostHog\Application\Service\Event\EventInstanceService;
use WPML\PHP\Exception\RemoteException;

class TeaUpgradeDismissedTracker {

  private $funnelProps;

  private $configService;

  private $eventInstanceService;

  private $captureEventService;


  public function __construct(
    TeaUpgradeFunnelProps $funnelProps,
    ConfigService $configService,
    EventInstanceService $eventInstanceService,
    CaptureEventService $captureEventService
  ) {
    $this->funnelProps          = $funnelProps;
    $this->configService        = $configService;
    $this->eventInstanceService = $eventInstanceService;
    $this->captureEventService  = $captureEventService;
  }


  public function trackDismissed( string $surface, array $noticeData ): void {
    $props = array_merge(
      $this->funnelProps->build( $surface, $noticeData ),
      [ 'source' => 'tea_notice' ]
    );

    try {
      $event = $this->eventInstanceService->getCustomTEAEvent(
        'tea_notice_dismissed',
        $props
      );
      $this->captureEventService->capture( $this->configService->create(), $event );
    } catch ( RemoteException $e ) {
    } catch ( \Throwable $e ) {
    }
  }


}
