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
use WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application\Endpoint\DismissTeaUpgradeNoticeController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\CancelJobs\CancelJobsController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetLocalTranslators\GetLocalTranslatorsController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetUnsolvableJobs\GetUnsolvableJobsController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\ResendUnsolvableJobs\ResendUnsolvableJobsController;
use WPML\UserInterface\Web\Legacy\Component\LanguagePanel\Permission\EditPostPermission;
use WPML\UserInterface\Web\Legacy\Component\LanguagePanel\Permission\EditPostsPermission;
use WPML\UserInterface\Web\Legacy\Component\LanguagePanel\Permission\RootPagePermission;
use WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint\AssignRootPageController;
use WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint\GetPostLanguagePanelController;
use WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint\GetPostsLanguagePanelController;
use WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint\MakeDuplicatesController;
use WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint\ResetDuplicationController;
use WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint\SetDuplicationController;
use WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint\SetEditorModeController;
use WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint\SetTranslationOfController;
use WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint\SwitchLanguageController;
use WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint\ToggleShowTranslationsController;
use WPML\UserInterface\Web\Core\Component\LanguagePanel\Application\Endpoint\TranslateController;
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
  'getpostlanguagepanel' => [
    'path'       => '/posts/(?P<id>\d+)/language-panel',
    'handler'    => GetPostLanguagePanelController::class,
    'method'     => 'GET',
    'capability' => 'edit_posts',
    'permission' => EditPostPermission::class,
    'args'       => [
      'id'          => [ 'type' => 'integer' ],
      'trid'        => [ 'type' => 'integer' ],
      'lang'        => [ 'type' => 'string' ],
      'source_lang' => [ 'type' => 'string' ],
      'screen'      => [ 'type' => 'string' ],
    ],
  ],
  'getpostslanguagepanel' => [
    'path'       => '/posts/language-panel',
    'handler'    => GetPostsLanguagePanelController::class,
    'method'     => 'GET',
    'capability' => 'edit_posts',
    'permission' => EditPostsPermission::class,
    'args'       => [
      'ids' => [
        'type'     => 'array',
        'items'    => [ 'type' => 'integer' ],
        'maxItems' => 100,
        'required' => true,
      ],
    ],
  ],

  'switchpostlanguage' => [
    'path'       => '/posts/(?P<id>\d+)/language',
    'handler'    => SwitchLanguageController::class,
    'method'     => 'POST',
    'capability' => 'edit_posts',
    'permission' => EditPostPermission::class,
    'args'       => [
      'id'       => [ 'type' => 'integer' ],
      'language' => [ 'type' => 'string', 'required' => true ],
    ],
  ],
  'setposttranslationof' => [
    'path'       => '/posts/(?P<id>\d+)/translation-of',
    'handler'    => SetTranslationOfController::class,
    'method'     => 'POST',
    'capability' => 'edit_posts',
    'permission' => EditPostPermission::class,
    'args'       => [
      'id'          => [ 'type' => 'integer' ],
      'original_id' => [ 'type' => [ 'integer', 'string' ], 'required' => true ],
    ],
  ],
  'makepostduplicates' => [
    'path'       => '/posts/(?P<id>\d+)/duplicates',
    'handler'    => MakeDuplicatesController::class,
    'method'     => 'POST',
    'capability' => 'edit_posts',
    'permission' => EditPostPermission::class,
    'args'       => [
      'id'        => [ 'type' => 'integer' ],
      'languages' => [
        'type'     => 'array',
        'items'    => [ 'type' => 'string' ],
        'minItems' => 1,
        'required' => true,
      ],
    ],
  ],
  'setpostduplication' => [
    'path'       => '/posts/(?P<id>\d+)/duplication',
    'handler'    => SetDuplicationController::class,
    'method'     => 'POST',
    'capability' => [ 'translate', 'manage_translations' ],
    'permission' => EditPostPermission::class,
    'args'       => [
      'id' => [ 'type' => 'integer' ],
    ],
  ],
  'resetpostduplication' => [
    'path'       => '/posts/(?P<id>\d+)/duplication',
    'handler'    => ResetDuplicationController::class,
    'method'     => 'DELETE',
    'capability' => [ 'translate', 'manage_translations' ],
    'permission' => EditPostPermission::class,
    'args'       => [
      'id' => [ 'type' => 'integer' ],
    ],
  ],
  'setposteditormode' => [
    'path'       => '/posts/(?P<id>\d+)/editor-mode',
    'handler'    => SetEditorModeController::class,
    'method'     => 'POST',
    'capability' => 'edit_posts',
    'args'       => [
      'id'                       => [ 'type' => 'integer' ],
      'editor'                   => [ 'type' => 'string', 'enum' => [ 'dashboard', 'wpml', 'native' ], 'required' => true ],
      'scope'                    => [ 'type' => 'string', 'enum' => [ 'global', 'all_posts_of_type', 'this_post' ], 'required' => true ],
      'is_switching_wpml_native' => [ 'type' => 'boolean' ],
    ],
  ],
  'togglepostshowtranslations' => [
    'path'       => '/posts/(?P<id>\d+)/show-translations',
    'handler'    => ToggleShowTranslationsController::class,
    'method'     => 'POST',
    'capability' => 'manage_options',
    'permission' => EditPostPermission::class,
    'args'       => [
      'id'   => [ 'type' => 'integer' ],
      'show' => [ 'type' => 'boolean' ],
    ],
  ],
  'assignpostrootpage' => [
    'path'       => '/posts/(?P<id>\d+)/root-page',
    'handler'    => AssignRootPageController::class,
    'method'     => 'POST',
    'capability' => 'wpml_manage_languages',
    'permission' => RootPagePermission::class,
    'args'       => [
      'id' => [ 'type' => 'integer' ],
    ],
  ],
  'translatepost' => [
    'path'       => '/posts/(?P<id>\d+)/translate',
    'handler'    => TranslateController::class,
    'method'     => 'POST',
    'capability' => [ 'translate', 'manage_translations' ],
    'args'       => [
      'id'         => [ 'type' => 'integer' ],
      'language'   => [ 'type' => 'string', 'required' => true ],
      'verb'       => [ 'type' => 'string', 'enum' => [ 'translate', 'edit', 'review' ] ],
      'currentUrl' => [ 'type' => 'string' ],
    ],
  ],

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
  'dismissteaupgradenotice' => [
    'path'    => '/dismiss-tea-upgrade-notice',
    'handler' => DismissTeaUpgradeNoticeController::class,
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
