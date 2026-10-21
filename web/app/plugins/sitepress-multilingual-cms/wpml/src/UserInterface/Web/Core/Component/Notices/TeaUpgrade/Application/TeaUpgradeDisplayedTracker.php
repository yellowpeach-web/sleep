<?php

namespace WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application;

use WPML\Core\Component\PostHog\Application\Service\Config\ConfigService;
use WPML\Core\Component\PostHog\Application\Service\Event\CaptureEventService;
use WPML\Core\Component\PostHog\Application\Service\Event\EventInstanceService;
use WPML\PHP\Exception\RemoteException;

class TeaUpgradeDisplayedTracker {

  private static $tracked = [];

  private $noticeDataProvider;

  private $funnelProps;

  private $configService;

  private $eventInstanceService;

  private $captureEventService;


  public function __construct(
    TeaUpgradeNoticeDataProvider $noticeDataProvider,
    TeaUpgradeFunnelProps $funnelProps,
    ConfigService $configService,
    EventInstanceService $eventInstanceService,
    CaptureEventService $captureEventService
  ) {
    $this->noticeDataProvider  = $noticeDataProvider;
    $this->funnelProps         = $funnelProps;
    $this->configService       = $configService;
    $this->eventInstanceService = $eventInstanceService;
    $this->captureEventService = $captureEventService;
  }


  public function trackDisplayed( string $surface ): void {
    if ( isset( self::$tracked[ $surface ] ) ) {
      return;
    }

    $noticeData = $this->noticeDataProvider->get();
    if ( ! $noticeData['isEligible'] ) {
      return;
    }

    self::$tracked[ $surface ] = true;

    $props = array_merge(
      $this->funnelProps->build( $surface, $noticeData ),
      [ 'source' => 'tea_notice' ]
    );

    try {
      $event = $this->eventInstanceService->getCustomTEAEvent(
        'tea_notice_displayed',
        $props
      );
      $this->captureEventService->capture( $this->configService->create(), $event );
    } catch ( RemoteException $e ) {
    } catch ( \Throwable $e ) {
    }
  }


}
