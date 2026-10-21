<?php

namespace WPML\Core\Component\PostHog\Application\Service;

use WPML\Core\Component\PostHog\Application\Configuration\PostHogOptOutRecordingConfigInterface;
use WPML\Core\Component\PostHog\Application\Repository\PostHogOptOutRecordingStateRepositoryInterface;
use WPML\Core\Component\PostHog\Application\Repository\PostHogStateRepositoryInterface;
use WPML\Core\Component\PostHog\Domain\TrackingMode;

class CountDashboardSessionService {

  const STOP_REASON_QUOTA       = 'quota_reached';
  const STOP_REASON_TEA_ENABLED = 'tea_enabled';
  const STOP_REASON_EXPIRED     = 'expired';

  private $repository;

  private $config;

  private $stateRepository;

  private $checkService;


  public function __construct(
    PostHogOptOutRecordingStateRepositoryInterface $repository,
    PostHogOptOutRecordingConfigInterface $config,
    PostHogStateRepositoryInterface $stateRepository,
    CheckPostHogShouldRecordService $checkService
  ) {
    $this->repository      = $repository;
    $this->config          = $config;
    $this->stateRepository = $stateRepository;
    $this->checkService    = $checkService;
  }


  public function count( string $sessionId ): bool {
    if ( $sessionId === '' || ! $this->repository->isActive() ) {
      return false;
    }

    if ( $this->retryPendingStop() ) {
      return false;
    }

    if ( $this->stateRepository->getTrackingMode() !== TrackingMode::ALL ) {
      return false;
    }

    if ( $this->repository->getDashboardRecordingCount() >= $this->config->getDashboardSessionLimit() ) {
      $this->stop( self::STOP_REASON_QUOTA );
      return false;
    }

    if ( $this->repository->isWindowExpired() ) {
      $this->stop( self::STOP_REASON_EXPIRED );
      return false;
    }

    $counted = $this->repository->recordSession( $sessionId );

    if ( $counted && $this->repository->getDashboardRecordingCount() >= $this->config->getDashboardSessionLimit() ) {
      $this->stop( self::STOP_REASON_QUOTA );
    }

    return $counted;
  }


  public function stopForTeaEnabled() {
    if ( ! $this->config->isEarlyStopOnEnable() ) {
      return;
    }

    if ( ! $this->repository->isActive() ) {
      return;
    }

    $this->stop( self::STOP_REASON_TEA_ENABLED );
  }


  public function getCount(): int {
    return $this->repository->getDashboardRecordingCount();
  }


  public function isActive(): bool {
    return $this->repository->isActive();
  }


  public function isCompleted(): bool {
    return $this->repository->isCompleted();
  }


  private function forceRefresh( array $context ): bool {
    try {
      return $this->checkService->run( true, $context );
    } catch ( \Throwable $e ) {
      unset( $e );
      return false;
    }
  }


  public function retryPendingStop(): bool {
    $reason = $this->repository->getPendingStopReason();

    if ( $reason === null ) {
      return false;
    }

    $this->stop( $reason );

    return true;
  }


  private function stop( string $reason ): void {
    $this->repository->markPendingStop( $reason );

    if ( ! $this->forceRefresh( $this->getStopContext( $reason ) ) ) {
      return;
    }

    $this->repository->markCompleted();
  }


  private function getStopContext( string $reason ): array {
    if ( $reason === self::STOP_REASON_QUOTA ) {
      return [ 'record_optout_dashboard_done' => true ];
    }

    return [];
  }


}
