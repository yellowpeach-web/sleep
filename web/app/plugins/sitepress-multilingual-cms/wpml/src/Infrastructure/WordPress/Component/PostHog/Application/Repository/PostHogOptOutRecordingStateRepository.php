<?php

namespace WPML\Infrastructure\WordPress\Component\PostHog\Application\Repository;

use WPML\Core\Component\PostHog\Application\Configuration\PostHogOptOutRecordingConfigInterface;
use WPML\Core\Component\PostHog\Application\Repository\PostHogOptOutRecordingStateRepositoryInterface;
use WPML\Core\Port\Persistence\OptionsInterface;

class PostHogOptOutRecordingStateRepository implements PostHogOptOutRecordingStateRepositoryInterface {

  const STARTED_AT_KEY        = 'wpml_posthog_optout_started_at';
  const COUNT_KEY             = 'wpml_posthog_dashboard_recording_count';
  const COUNTED_SESSIONS_KEY  = 'wpml_posthog_counted_dashboard_sessions';
  const COMPLETED_KEY         = 'wpml_posthog_optout_recording_completed';
  const PENDING_STOP_KEY      = 'wpml_posthog_optout_pending_stop_reason';

  const SECONDS_PER_DAY = 86400;

  private $options;

  private $config;


  public function __construct(
    OptionsInterface $options,
    PostHogOptOutRecordingConfigInterface $config
  ) {
    $this->options = $options;
    $this->config  = $config;
  }


  public function activate() {
    $this->options->save( self::STARTED_AT_KEY, time(), false );
    $this->options->save( self::COUNT_KEY, 0, false );
    $this->options->save( self::COUNTED_SESSIONS_KEY, [], false );
    $this->options->delete( self::COMPLETED_KEY );
    $this->options->delete( self::PENDING_STOP_KEY );
  }


  public function clear() {
    $this->options->delete( self::STARTED_AT_KEY );
    $this->options->delete( self::COUNT_KEY );
    $this->options->delete( self::COUNTED_SESSIONS_KEY );
    $this->options->delete( self::COMPLETED_KEY );
    $this->options->delete( self::PENDING_STOP_KEY );
  }


  public function isActive(): bool {
    return $this->getStartedAt() !== null && ! $this->isCompleted();
  }


  public function getStartedAt(): ?int {
    $value = $this->options->get( self::STARTED_AT_KEY, null );

    return is_numeric( $value ) ? (int) $value : null;
  }


  public function getDashboardRecordingCount(): int {
    return count( $this->getCountedSessions() );
  }


  public function getCountedSessions(): array {
    $raw = $this->options->get( self::COUNTED_SESSIONS_KEY, [] );

    if ( ! is_array( $raw ) ) {
      return [];
    }

    $sessions = [];
    foreach ( $raw as $item ) {
      if ( is_string( $item ) && $item !== '' ) {
        $sessions[] = $item;
      }
    }

    return array_values( array_unique( $sessions ) );
  }


  public function hasCountedSession( string $sessionId ): bool {
    return in_array( $sessionId, $this->getCountedSessions(), true );
  }


  public function recordSession( string $sessionId ): bool {
    if ( $sessionId === '' || $this->hasCountedSession( $sessionId ) ) {
      return false;
    }

    $sessions   = $this->getCountedSessions();
    $sessions[] = $sessionId;

    $this->options->save( self::COUNTED_SESSIONS_KEY, $sessions, false );
    $this->options->save( self::COUNT_KEY, count( $sessions ), false );

    return true;
  }


  public function isCompleted(): bool {
    return (bool) $this->options->get( self::COMPLETED_KEY, false );
  }


  public function markCompleted() {
    $this->options->save( self::COMPLETED_KEY, true, false );
    $this->clearPendingStop();
  }


  public function markPendingStop( string $reason ) {
    if ( $reason === '' ) {
      return;
    }

    $this->options->save( self::PENDING_STOP_KEY, $reason, false );
  }


  public function getPendingStopReason(): ?string {
    $reason = $this->options->get( self::PENDING_STOP_KEY, null );

    return is_string( $reason ) && $reason !== '' ? $reason : null;
  }


  public function clearPendingStop() {
    $this->options->delete( self::PENDING_STOP_KEY );
  }


  public function isWindowExpired(): bool {
    $startedAt = $this->getStartedAt();

    if ( $startedAt === null ) {
      return false;
    }

    $windowSeconds = $this->config->getWindowDays() * self::SECONDS_PER_DAY;

    return time() - $startedAt > $windowSeconds;
  }


}
