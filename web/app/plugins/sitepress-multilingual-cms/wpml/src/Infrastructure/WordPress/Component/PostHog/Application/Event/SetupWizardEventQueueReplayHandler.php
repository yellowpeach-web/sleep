<?php

namespace WPML\Infrastructure\WordPress\Component\PostHog\Application\Event;

use WPML\Core\Component\PostHog\Application\Repository\SetupWizardEventQueueRepositoryInterface;
use WPML\Core\Component\PostHog\Application\Service\Config\ConfigService;
use WPML\Core\Component\PostHog\Application\Service\Event\CaptureEventService;
use WPML\Core\Component\PostHog\Application\Service\Event\SetupWizardEventQueueService;
use WPML\Core\Component\PostHog\Domain\Event\Custom\Event as CustomEvent;
use WPML\DicInterface;
use WPML\Infrastructure\WordPress\Component\PostHog\Domain\Event\Capture;
use WPML\PHP\Exception\RemoteException;

class SetupWizardEventQueueReplayHandler {

  const RETRY_DELAY_SECONDS = 300;

  const MAX_REPLAY_PER_RUN = 10;

  private $dic;


  public function __construct( DicInterface $dic ) {
    $this->dic = $dic;
  }


  public function handle() {
    $queueService = $this->dic->make( SetupWizardEventQueueService::class );

    $queueService->cleanupStale();

    $batch = array_slice( $queueService->getPending(), 0, self::MAX_REPLAY_PER_RUN );

    foreach ( $batch as $entry ) {
      $this->replayEntry( $queueService, $entry );
    }

    if ( $queueService->getPending() ) {
      wp_schedule_single_event(
        time() + self::RETRY_DELAY_SECONDS,
        SetupWizardEventQueueReplayScheduler::REPLAY_HOOK
      );
    }
  }


  private function replayEntry( SetupWizardEventQueueService $queueService, array $entry ) {
    try {
      $event = new CustomEvent(
        $entry['event_name'],
        $this->withReplayMetadata( $entry )
      );

      $captureService = $this->dic->make(
        CaptureEventService::class,
        [ ':captureEvent' => $this->dic->make( Capture::class ) ]
      );

      $sent = $captureService->capture(
        ( new ConfigService() )->create(),
        $event,
        $this->replayPersonProps( $entry )
      );

      if ( $sent ) {
        $queueService->markSuccess( $entry['id'] );
        return;
      }

      $queueService->markFailed( $entry['id'], 'capture returned false' );
    } catch ( RemoteException $e ) {
      $queueService->markFailed( $entry['id'], $e->getMessage() );
    } catch ( \Throwable $e ) {
      $queueService->markFailed( $entry['id'], get_class( $e ) . ': ' . $e->getMessage() );
    }
  }


  private function replayPersonProps( array $entry ) {
    return array_filter(
      $entry['person_props'],
      function ( $value ) {
        return $value !== null && $value !== false && $value !== '';
      }
    );
  }


  private function withReplayMetadata( array $entry ) {
    return array_merge(
      $entry['properties'],
      [
        'queued_event'       => true,
        'original_event_time' => $entry['created_at'] > 0 ? gmdate( 'c', $entry['created_at'] ) : null,
        'replay_time'         => gmdate( 'c' ),
        'queue_source'        => $entry['source'],
      ]
    );
  }

}
