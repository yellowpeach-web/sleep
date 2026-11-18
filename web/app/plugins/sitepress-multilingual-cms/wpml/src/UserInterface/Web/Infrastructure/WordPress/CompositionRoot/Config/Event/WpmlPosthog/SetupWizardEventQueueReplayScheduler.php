<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\WpmlPosthog;

use WPML\Core\Component\PostHog\Application\Service\Event\CaptureEventService;
use WPML\Core\Component\PostHog\Application\Service\Event\SetupWizardEventQueueService;
use WPML\DicInterface;
use WPML\Infrastructure\PluginFilesMatchLoadedVersionGate;
use WPML\Infrastructure\WordPress\Component\PostHog\Application\Event\SetupWizardEventQueueReplayHandler;
use WPML\Infrastructure\WordPress\Component\PostHog\Domain\Event\Capture;

class SetupWizardEventQueueReplayScheduler {

  use PluginFilesMatchLoadedVersionGate;

  const EVENT_NAME = 'wpml_posthog_tracking_mode_resolved';

  const REPLAY_DELAY_SECONDS = 60;

  private $dic;


  public function __construct( DicInterface $dic, ?callable $filesMatchLoadedVersion = null ) {
    $this->dic                     = $dic;
    $this->filesMatchLoadedVersion = $filesMatchLoadedVersion;
    $this->register();
  }


  public function register() {
    add_action(
      self::EVENT_NAME,
      function ( string $trackingMode ) {
        if ( ! $this->filesMatchLoadedVersion() ) {
          return;
        }
        if ( SetupWizardEventQueueReplayHandler::REPLAY_TRACKING_MODE === $trackingMode ) {
          if ( ! wp_next_scheduled( SetupWizardEventQueueReplayHandler::REPLAY_HOOK ) ) {
            wp_schedule_single_event( time() + self::REPLAY_DELAY_SECONDS, SetupWizardEventQueueReplayHandler::REPLAY_HOOK );
          }
          return;
        }

        wp_clear_scheduled_hook( SetupWizardEventQueueReplayHandler::REPLAY_HOOK );
        $this->getQueueService()->discard();
      }
    );

    add_action(
      SetupWizardEventQueueReplayHandler::REPLAY_HOOK,
      function () {
        if ( ! $this->filesMatchLoadedVersion() ) {
          return;
        }
        $this->getReplayHandler()->handle();
      }
    );
  }


  private function getQueueService(): SetupWizardEventQueueService {
    return $this->dic->make( SetupWizardEventQueueService::class );
  }


  private function getReplayHandler(): SetupWizardEventQueueReplayHandler {
    return new SetupWizardEventQueueReplayHandler(
      $this->getQueueService(),
      function () {
        return $this->dic->make(
          CaptureEventService::class,
          [ ':captureEvent' => $this->dic->make( Capture::class ) ]
        );
      }
    );
  }


}
