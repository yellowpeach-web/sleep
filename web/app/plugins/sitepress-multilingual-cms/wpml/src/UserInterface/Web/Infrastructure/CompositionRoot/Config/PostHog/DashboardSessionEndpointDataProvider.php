<?php

namespace WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\PostHog;

use WPML\UserInterface\Web\Core\Component\PostHog\Application\Endpoint\DashboardSession\CountDashboardSessionController;
use WPML\UserInterface\Web\Core\SharedKernel\Config\Endpoint\MethodType;

class DashboardSessionEndpointDataProvider {

  const ID = 'wpmlPostHogDashboardSession';

  const PATH = '/posthog/dashboard-session';

  const HANDLER = CountDashboardSessionController::class;

  const METHOD = MethodType::POST;
}
