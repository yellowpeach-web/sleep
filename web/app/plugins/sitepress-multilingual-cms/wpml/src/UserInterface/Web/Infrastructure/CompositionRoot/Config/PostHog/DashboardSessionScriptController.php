<?php

namespace WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\PostHog;

use WPML\Core\Component\PostHog\Application\Query\TranslationDashboardPageQueryInterface;
use WPML\Core\Component\PostHog\Application\Repository\PostHogOptOutRecordingStateRepositoryInterface;
use WPML\UserInterface\Web\Core\Port\Script\ScriptDataProviderInterface;
use WPML\UserInterface\Web\Core\Port\Script\ScriptPrerequisitesInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Config\Endpoint\Endpoint;
use WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\ApiInterface;

class DashboardSessionScriptController implements ScriptPrerequisitesInterface, ScriptDataProviderInterface {

  private $endpoint;

  private $api;

  private $dashboardPage;

  private $optOutState;


  public function __construct(
    ApiInterface $api,
    TranslationDashboardPageQueryInterface $dashboardPage,
    PostHogOptOutRecordingStateRepositoryInterface $optOutState
  ) {
    $this->api           = $api;
    $this->dashboardPage = $dashboardPage;
    $this->optOutState   = $optOutState;
  }


  public function scriptPrerequisitesMet(): bool {
    return $this->dashboardPage->isCurrent() && $this->optOutState->isActive();
  }


  public function jsWindowKey(): string {
    return 'wpmlPostHogDashboardSession';
  }


  public function initialScriptData(): array {
    return [
      'route' => $this->api->getFullUrl( $this->getEndpoint() ),
      'nonce' => $this->api->nonce(),
    ];
  }


  private function getEndpoint(): Endpoint {
    if ( $this->endpoint === null ) {
      $this->endpoint = new Endpoint(
        DashboardSessionEndpointDataProvider::ID,
        DashboardSessionEndpointDataProvider::PATH
      );
      $this->endpoint->setMethod( DashboardSessionEndpointDataProvider::METHOD );
    }

    return $this->endpoint;
  }


}
