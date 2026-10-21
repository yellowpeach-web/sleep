<?php

namespace WPML\Core\Component\PostHog\Application\Service;

use WPML\Core\Component\PostHog\Application\Repository\PostHogCacheStateRepositoryInterface;
use WPML\Core\Component\PostHog\Application\Repository\PostHogOptOutRecordingStateRepositoryInterface;
use WPML\Core\Component\PostHog\Application\Repository\PostHogStateRepositoryInterface;
use WPML\Core\Component\PostHog\Application\Event\PostHogTrackingModeResolved;
use WPML\Core\Component\Translation\Application\Repository\SettingsRepository;
use WPML\Core\Port\Event\DispatcherInterface;
use WPML\Core\Port\PluginInterface;
use WPML\Core\SharedKernel\Component\Installer\Application\Query\WpmlSiteKeyQueryInterface;
use WPML\Core\SharedKernel\Component\WpmlOrgClient\Application\Service\PostHogRecording\PostHogRecordingService;

class CheckPostHogShouldRecordService {

  const DEFAULT_RECHECK_TTL_SECONDS = 86400;

  const TEA_STATE_ENABLED  = 'enabled';
  const TEA_STATE_DISABLED = 'disabled';

  private $siteKeyQuery;

  private $postHogRecordingService;

  private $postHogStateRepository;

  private $cacheStateRepository;

  private $retryService;

  private $plugin;

  private $settingsRepository;

  private $optOutStateRepository;

  private $dispatcher;


  public function __construct(
    WpmlSiteKeyQueryInterface $siteKeyQuery,
    PostHogRecordingService $postHogRecordingService,
    PostHogStateRepositoryInterface $postHogStateRepository,
    PostHogCacheStateRepositoryInterface $cacheStateRepository,
    RetryService $retryService,
    PluginInterface $plugin,
    SettingsRepository $settingsRepository,
    PostHogOptOutRecordingStateRepositoryInterface $optOutStateRepository,
    DispatcherInterface $dispatcher
  ) {
    $this->siteKeyQuery            = $siteKeyQuery;
    $this->postHogRecordingService = $postHogRecordingService;
    $this->postHogStateRepository  = $postHogStateRepository;
    $this->cacheStateRepository    = $cacheStateRepository;
    $this->retryService            = $retryService;
    $this->plugin                  = $plugin;
    $this->settingsRepository      = $settingsRepository;
    $this->optOutStateRepository   = $optOutStateRepository;
    $this->dispatcher              = $dispatcher;
  }


  public function run( bool $forceRefresh = false, array $context = [] ): bool {
    $siteKey = $this->siteKeyQuery->get();

    if ( ! $siteKey ) {
      return false;
    }

    if ( $this->retryService->isInRetryMode() ) {
      if ( ! $this->retryService->shouldRetry() ) {
        return false;
      }
    } elseif ( ! $forceRefresh && ! $this->cacheStateRepository->isStale( $this->getRecheckTtlSeconds() ) ) {
      return true;
    }

    if ( ! $this->cacheStateRepository->acquireProcessingLock() ) {
      return true;
    }

    try {
      $previousMode = $this->postHogStateRepository->getTrackingMode();
      $wpmlVersion  = $this->plugin->getVersion();
      $teaState     = $this->getTeaState();

      $result = $this->postHogRecordingService->run( $siteKey, 'default', $wpmlVersion, $teaState, $context );

      if ( $result['isResponseError'] ) {
        $this->handleResponseError();
        return false;
      }

      $this->cacheStateRepository->setLastChecked( time() );

      if ( $result['trackingMode'] !== $previousMode ) {
        $this->postHogStateRepository->setTrackingMode( $result['trackingMode'] );

        $this->dispatcher->dispatch(
          new PostHogTrackingModeResolved( $result['trackingMode'] )
        );
      }

      $this->reconcileOptOutCohort( $result );

      $this->retryService->reset();
      return true;
    } finally {
      $this->cacheStateRepository->releaseProcessingLock();
    }
  }


  public function handleResponseError() {
    $this->retryService->incrementAttempt();

    if ( $this->retryService->hasExceededMaxAttempts() ) {
      $this->retryService->reset();
      $this->cacheStateRepository->setLastChecked( time() );
    }
  }


  private function getTeaState(): string {
    return $this->settingsRepository->getSettings()->getTranslateEverything()->isEnabled()
      ? self::TEA_STATE_ENABLED
      : self::TEA_STATE_DISABLED;
  }


  private function reconcileOptOutCohort( array $result ) {
    $recordOptOutDashboard = $result['recordOptOutDashboard'] ?? null;

    if ( $recordOptOutDashboard === null ) {
      return;
    }

    if ( $recordOptOutDashboard === true ) {
      if ( ! $this->optOutStateRepository->isActive() && ! $this->optOutStateRepository->isCompleted() ) {
        $this->optOutStateRepository->activate();
      }

      return;
    }

    if (
      $this->optOutStateRepository->isActive()
      && $this->optOutStateRepository->getPendingStopReason() === null
    ) {
      $this->optOutStateRepository->clear();
    }
  }


  private function getRecheckTtlSeconds(): int {
    try {
      return defined( 'WPML_POSTHOG_RECHECK_TTL_SECONDS' )
        ? (int) constant( 'WPML_POSTHOG_RECHECK_TTL_SECONDS' )
        : self::DEFAULT_RECHECK_TTL_SECONDS;
    } catch ( \Throwable $e ) {
      return self::DEFAULT_RECHECK_TTL_SECONDS;
    }
  }


}
