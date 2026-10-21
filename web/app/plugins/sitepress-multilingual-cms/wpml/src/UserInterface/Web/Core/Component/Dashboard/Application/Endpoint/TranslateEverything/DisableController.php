<?php

namespace WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\TranslateEverything;

use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\ReleaseSummary;
use WPML\Core\Component\Translation\Application\Service\SettingsService;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\AtePingerInterface;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\TeaLoggerInterface;

class DisableController implements EndpointInterface {

  private $settingsService;

  private $atePinger;

  private $logger;


  public function __construct(
    SettingsService $settingsService,
    AtePingerInterface $atePinger,
    TeaLoggerInterface $logger
  ) {
    $this->settingsService = $settingsService;
    $this->atePinger       = $atePinger;
    $this->logger          = $logger;
  }


  public function handle( $requestData = null ): array {
    $this->logger->beginDashboardDisable();

    try {
      $this->settingsService->disableTranslateEverything();

      $this->atePinger->notifyTeaDisabled( AtePingerInterface::TRIGGER_DASHBOARD_DISABLE );

      $response = [ 'success' => true ];

      $release = apply_filters( 'wpml_tea_disable_release', null );

      if ( $release instanceof ReleaseSummary && ! $release->isEmpty() ) {
        $response['release'] = $release->toArray();
      }

      return $response;
    } finally {
      $this->logger->end();
    }
  }


}
