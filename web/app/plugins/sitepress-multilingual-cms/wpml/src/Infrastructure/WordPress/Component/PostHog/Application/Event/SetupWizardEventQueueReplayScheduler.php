<?php

namespace WPML\Infrastructure\WordPress\Component\PostHog\Application\Event;

use WPML\Core\Component\PostHog\Application\Service\Event\SetupWizardEventQueueService;
use WPML\Core\Component\PostHog\Domain\TrackingMode;
use WPML\DicInterface;
use WPML\Infrastructure\PluginFilesMatchLoadedVersionGate;

class SetupWizardEventQueueReplayScheduler {

  use PluginFilesMatchLoadedVersionGate;

  const EVENT_NAME = 'wpml_posthog_tracking_mode_resolved';

  const REPLAY_HOOK = 'wpml_posthog_setup_wizard_queue_replay';

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
        if ( TrackingMode::ALL === $trackingMode ) {
          if ( ! wp_next_scheduled( self::REPLAY_HOOK ) ) {
            wp_schedule_single_event( time() + self::REPLAY_DELAY_SECONDS, self::REPLAY_HOOK );
          }
          return;
        }

        wp_clear_scheduled_hook( self::REPLAY_HOOK );
        $this->getQueueService()->discard();
      }
    );

    add_action(
      self::REPLAY_HOOK,
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
    return $this->dic->make(
      SetupWizardEventQueueReplayHandler::class,
      [ ':dic' => $this->dic ]
    );
  }


}
