<?php

namespace WPML\UserInterface\Web\Core\Component\ATE\Application\Endpoint;

use WPML\Core\Component\Translation\Application\Service\SettingsService;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\AteActivationInterface;

class EnableAteController implements EndpointInterface {

  private $settingsService;

  private $ateActivation;


  public function __construct(
    SettingsService $settingsService,
    AteActivationInterface $ateActivation
  ) {
    $this->settingsService = $settingsService;
    $this->ateActivation   = $ateActivation;
  }


  public function handle( $requestData = null ): array {
    $this->settingsService->enableATE();

    $activated = $this->ateActivation->activate();

    return [ 'success' => $activated ];
  }


}
