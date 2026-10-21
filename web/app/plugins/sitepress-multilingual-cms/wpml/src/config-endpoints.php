<?php

use WPML\Infrastructure\WordPress\SharedKernel\Server\Application\Endpoint\RestStatus\RestStatusController;
use WPML\UserInterface\Web\Core\Component\ATE\Application\Endpoint\DismissPendingSuggestionsNudgeController;
use WPML\UserInterface\Web\Core\Component\ATE\Application\Endpoint\DismissQueuedRetranslationReminderController;
use WPML\UserInterface\Web\Core\Component\ATE\Application\Endpoint\EnableAteController;
use WPML\UserInterface\Web\Core\Component\ATE\Application\Endpoint\GetGlossaryCountController;
use WPML\UserInterface\Web\Core\Component\ATE\Application\Endpoint\GetNormalizedSuggestionsCountController;
use WPML\UserInterface\Web\Core\Component\Communication\Application\Endpoint\DismissNoticeController;
use WPML\UserInterface\Web\Core\Component\Communication\Application\Endpoint\DismissNoticePerUserController;
use WPML\UserInterface\Web\Core\Component\Communication\Application\Endpoint\GetDismissedNoticesController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\CancelJobs\CancelJobsController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetLocalTranslators\GetLocalTranslatorsController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetUnsolvableJobs\GetUnsolvableJobsController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\ResendUnsolvableJobs\ResendUnsolvableJobsController;
use WPML\UserInterface\Web\Core\Component\MinimumRequirements\Application\EndPoint\MinimumRequirements\GetRequirementsController;
use WPML\UserInterface\Web\Core\Component\PostHog\Application\Endpoint\Event\Capture\PostHogCaptureEventController;
use WPML\UserInterface\Web\Core\Component\PostHog\Application\Endpoint\Event\Capture\ProxyCaptureEventController;
use WPML\UserInterface\Web\Core\Component\PostHog\Application\Endpoint\Refresh\RefreshPostHogCacheController;
use WPML\UserInterface\Web\Core\Component\WpmlProxy\Application\Endpoint\GetWpmlProxyStatus\GetWpmlProxyStatusController;
use WPML\UserInterface\Web\Core\Component\WpmlProxy\Application\Endpoint\SetWpmlProxyStatus\SetWpmlProxyStatusController;
use WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\ContentStats\EndpointDataProvider;
use WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\PostHog\DashboardSessionEndpointDataProvider;
use WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\PostHog\EndpointDataProvider as PostHogEndpointDataProvider;


return [
  'getlocaltranslators' => [
    'path'    => '/getlocaltranslators',
    'handler' => GetLocalTranslatorsController::class,
    'method'  => 'GET',
  ],
  'getglossarycounts' => [
    'path' => '/glossary-count',
    'handler' => GetGlossaryCountController::class,
    'method' => 'GET',
    'capability' => 'translate',
  ],
  'getnormalizedsuggestionscount' => [
    'path' => '/normalized-suggestions-count',
    'handler' => GetNormalizedSuggestionsCountController::class,
    'method' => 'GET',
    'capability' => 'translate',
  ],
  'dismissqueuedretranslationreminder' => [
    'path'       => '/dismiss-queued-retranslation-reminder',
    'handler'    => DismissQueuedRetranslationReminderController::class,
    'method'     => 'POST',
    'capability' => 'translate',
  ],
  'dismisspendingsuggestionsnudge' => [
    'path'       => '/dismiss-pending-suggestions-nudge',
    'handler'    => DismissPendingSuggestionsNudgeController::class,
    'method'     => 'POST',
    'capability' => 'translate',
  ],
  'enableate' => [
    'path' => '/enable-ate',
    'handler' => EnableAteController::class,
    'method' => 'POST',
  ],

  'getdismissednotices' => [
    'path' => '/get-dismissed-notices',
    'handler' => GetDismissedNoticesController::class,
    'method' => 'GET',
  ],
  'getminimumrequirements' => [
    'path'    => '/minimum-requirements',
    'handler' => GetRequirementsController::class,
    'method'  => 'GET',
  ],
  'reststatus' => [
    'path'       => '/rest/status',
    'handler'    => RestStatusController::class,
    'method'     => 'GET',
    'capability' => '__return_true',
  ],
  'posthogrefreshcache' => [
    'path'       => '/posthog/refresh-cache',
    'handler'    => RefreshPostHogCacheController::class,
    'method'     => 'POST',
    'capability' => '__return_true',
  ],
  'dismissnotice' => [
    'path' => '/dismiss-notice',
    'handler' => DismissNoticeController::class,
    'method' => 'POST',
  ],
  'dismissnoticeperuser' => [
    'path'    => '/dismiss-notice-per-user',
    'handler' => DismissNoticePerUserController::class,
    'method'  => 'POST',
  ],
  'canceljobs' => [
    'path'    => '/cancel-jobs',
    'handler' => CancelJobsController::class,
    'method'  => 'POST',
  ],
  'getunsolvablejobs' => [
    'path'    => '/unsolvable-jobs',
    'handler' => GetUnsolvableJobsController::class,
    'method'  => 'GET',
  ],
  'resendunsolvablejobs' => [
    'path'    => '/resend-unsolvable-jobs',
    'handler' => ResendUnsolvableJobsController::class,
    'method'  => 'POST',
  ],
  'wpmlposthogcaptureevent' => [
    'path'    => '/wpml/posthog/capture-event',
    'handler' => PostHogCaptureEventController::class,
    'method'  => 'POST',
  ],

  'proxycaptureevent' => [
    'path'    => '/capture/event',
    'handler' => ProxyCaptureEventController::class,
    'method'  => 'POST',
  ],

  'getwpmlproxystatus' => [
    'path'    => '/wpml-proxy/status',
    'handler' => GetWpmlProxyStatusController::class,
    'method'  => 'GET',
  ],
  'setwpmlproxystatus' => [
    'path'    => '/wpml-proxy/status',
    'handler' => SetWpmlProxyStatusController::class,
    'method'  => 'POST',
  ],

  EndpointDataProvider::ID => [
    'path'    => EndpointDataProvider::PATH,
    'handler' => EndpointDataProvider::HANDLER,
    'method'  => EndpointDataProvider::METHOD,
  ],

  PostHogEndpointDataProvider::ID => [
    'path'       => PostHogEndpointDataProvider::PATH,
    'handler'    => PostHogEndpointDataProvider::HANDLER,
    'method'     => PostHogEndpointDataProvider::METHOD,
    'capability' => 'read',
  ],

  DashboardSessionEndpointDataProvider::ID => [
    'path'    => DashboardSessionEndpointDataProvider::PATH,
    'handler' => DashboardSessionEndpointDataProvider::HANDLER,
    'method'  => DashboardSessionEndpointDataProvider::METHOD,
  ],

];
