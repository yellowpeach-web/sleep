<?php

namespace WPML\UserInterface\Web\Core\Component\Troubleshooting\Application\Endpoint;

use WPML\Core\Component\PostHog\Application\Repository\PostHogStateRepositoryInterface;
use WPML\Core\Component\PostHog\Application\Event\PostHogTrackingModeResolved;
use WPML\Core\Component\PostHog\Application\Service\CheckPostHogShouldRecordService;
use WPML\Core\Component\Translation\Application\Repository\SettingsRepository;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\Core\Port\Event\DispatcherInterface;
use WPML\Core\Port\PluginInterface;
use WPML\Core\SharedKernel\Component\WpmlOrgClient\Application\Service\PostHogRecording\PostHogRecordingService;

class UpdatePostHogStateController implements EndpointInterface {

  private $posthogStateRepository;

  private $postHogRecordingService;

  private $plugin;

  private $settingsRepository;

  private $dispatcher;


  public function __construct(
    PostHogStateRepositoryInterface $posthogStateRepository,
    PostHogRecordingService $postHogRecordingService,
    PluginInterface $plugin,
    SettingsRepository $settingsRepository,
    DispatcherInterface $dispatcher
  ) {
    $this->posthogStateRepository  = $posthogStateRepository;
    $this->postHogRecordingService = $postHogRecordingService;
    $this->plugin                  = $plugin;
    $this->settingsRepository      = $settingsRepository;
    $this->dispatcher              = $dispatcher;
  }


  public function handle( $requestData = null ): array {
    if (
      ! isset( $requestData['enabled'] ) ||
      ! isset( $requestData['siteKey'] ) ||
      ! is_bool( $requestData['enabled'] ) ||
      ! is_string( $requestData['siteKey'] )
    ) {
      return [
        'success' => false,
        'data'    => [
          'message' => 'Invalid request data',
          'enabled' => false,
        ]
      ];
    }

    $wpmlVersion = $this->plugin->getVersion();
    $teaState    = $this->settingsRepository->getSettings()->getTranslateEverything()->isEnabled()
      ? CheckPostHogShouldRecordService::TEA_STATE_ENABLED
      : CheckPostHogShouldRecordService::TEA_STATE_DISABLED;
    $enabling    = $requestData['enabled'];

    $result = $this->postHogRecordingService->run(
      $requestData['siteKey'],
      $enabling ? 'force_enable' : 'force_disable',
      $wpmlVersion,
      $teaState
    );

    if ( $result['isResponseError'] ) {
      if ( ! $enabling ) {
        $this->setTrackingMode( 'disabled' );
        return [
          'success' => true,
          'data'    => [
            'message'            => 'PostHog disabled locally; remote update failed',
            'enabled'            => false,
            'trackingMode'       => 'disabled',
            'remoteUpdateFailed' => true,
          ]
        ];
      }

      return [
        'success' => false,
        'data'    => [
          'message' => 'Failed to contact PostHog service',
          'enabled' => false,
        ]
      ];
    }

    $this->setTrackingMode( $result['trackingMode'] );

    return [
      'success' => true,
      'data'    => [
        'message'      => 'PostHog state updated',
        'enabled'      => $result['shouldRecord'],
        'trackingMode' => $result['trackingMode'],
      ]
    ];
  }


  private function setTrackingMode( string $trackingMode ) {
    $previousMode = $this->posthogStateRepository->getTrackingMode();

    $this->posthogStateRepository->setTrackingMode( $trackingMode );

    if ( $trackingMode !== $previousMode ) {
      $this->dispatcher->dispatch( new PostHogTrackingModeResolved( $trackingMode ) );
    }
  }


}
