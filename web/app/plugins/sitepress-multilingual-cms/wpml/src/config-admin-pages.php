<?php

namespace WPML;

use WPML\UserInterface\Web\Core\Component\ATE\Application\Endpoint\GetWebsiteContext\GetWebsiteContextController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\AutomaticTranslation\CancelAllAutomaticJobsController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\AutomaticTranslation\GetAutomaticJobsInProgressController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetHierarchicalPosts\GetHierarchicaPostsController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetLocalTranslatorById\GetTranslatorByIdController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetNeedsUpdateCreatedInCte\GetNeedsUpdateCreatedInCteController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetPopulatedItemSections\GetPopulatedItemSectionsController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetPostTaxonomies\GetPostTaxonomiesController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetPostTerms\GetPostTermsController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetPosts\GetPostControllerInterface;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetPosts\GetPostsCountController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetRemoteTranslationService\GetRemoteTranslationServiceController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetTranslationBatchDefaultName\GetTranslationBatchDefaultName;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetTranslationEditorType\GetTranslationEditorTypeController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetTranslationStatus\GetTranslationStatusController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetUntranslatedTypesCount\GetUntranslatedTypesCountController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetWordsToTranslate\GetCreditsPerWordController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetWordsToTranslate\GetWordsToTranslateForItemsController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetWordsToTranslate\GetWordsToTranslateForTypesController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\HasPostsUsingNativeEditor\HasPostsUsingNativeEditorController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\SaveTranslatorNote\SaveTranslatorNoteController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\SendToTranslation\SendToTranslationController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\SetReviewTranslationOption\SetReviewTranslationOptionController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\TranslateEverything\DisableController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\TranslateEverything\EnableController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\TranslateEverything\RecheckReachabilityController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\TranslateEverything\TranslateExistingContentController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\TranslationProxy\GetLastPickedUpController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\TranslationProxy\GetRemoteJobsCountController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\TranslationProxy\SendCommitRequestController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\ValidateSelectedTranslationMethods\ValidateSelectedTranslationMethodsController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\ValidateTranslationBatchName\ValidateTranslationBatchNameController;
use WPML\UserInterface\Web\Core\Component\Preferences\Application\Endpoint\GetEngines\GetEnginesController;
use WPML\UserInterface\Web\Core\Component\Preferences\Application\Endpoint\SaveAutomaticTranslationsSettings\SaveAutomaticTranslationsSettingsController;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings\AiTranslationActiveEngineController;
use WPML\UserInterface\Web\Core\Component\Support\Application\Endpoint\ActivateRepairToolsController;
use WPML\UserInterface\Web\Core\Component\Support\Application\Endpoint\CreatePluginReportController;
use WPML\UserInterface\Web\Core\Component\Troubleshooting\Application\Endpoint\ClearRemoteXMLConfigLogController;
use WPML\UserInterface\Web\Core\Component\Troubleshooting\Application\Endpoint\EnableAliasDomainController;
use WPML\UserInterface\Web\Core\Component\Troubleshooting\Application\Endpoint\RegisterAliasDomainController;
use WPML\UserInterface\Web\Core\Component\Troubleshooting\Application\Endpoint\ResetAliasDomainController;
use WPML\UserInterface\Web\Core\Component\Troubleshooting\Application\Endpoint\UpdatePostHogStateController;
use WPML\UserInterface\Web\Core\SharedKernel\Config\Endpoint\MethodType;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\ActivateUpdate\ActivateUpdateController;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Billing\BillingController;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Billing\BillingPageRequirements;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings\SettingsSectionDispatcher;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\SupportSubPageDispatcher;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool\AliasDomainsController;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations\AdminTextsTranslationController;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations\TranslationsTabStripDispatcher;

return [
  'tm/menu/main.php'                          => [
    /* translators: Name of the WPML Translations screen: the admin menu item and the page heading. */
    'title'                          => __( 'Translations', 'wpml' ),
    /* translators: Name of the WPML Translations screen: the admin menu item and the page heading. */
    'menuTitle'                      => __( 'Translations', 'wpml' ),
    'controller'                     => TranslationsTabStripDispatcher::class,
    'capability'                     => 'translate',
    'legacyParentId'                 => 'WPML',
    'position'                       => 1,
    // Taxonomy tabs, which a blog license (TM not allowed) still
    // blog-license sites, which bubbled Settings up to the top-level
    'requiresWPMLSetupToBeCompleted' => true,

    'scripts'                        => [
      [
        'id'            => 'wpml-notice-improve-translations',
        'src'           => 'public/js/notice-improve-translations.js',
        'dependencies'  => [ 'wpml-node-modules', 'wp-i18n', 'lodash' ],
      ],
      [
        'id'            => 'wpml-dashboard',
        'src'           => 'public/js/dashboard.js',
        'prerequisites' => TranslationsTabStripDispatcher::class,
        'dataProvider'  => TranslationsTabStripDispatcher::class,
        'dependencies'  => [ 'wpml-node-modules', 'wp-i18n', 'lodash' ],
        'components'    => [ 'ate', 'wpml-tea-calculation' ],
        'supportsHMR'   => true,
      ],
      [
        'id'            => 'wpml-notice-glossary',
        'src'           => 'public/js/notice-glossary.js',
        'prerequisites' => TranslationsTabStripDispatcher::class,
        'dependencies'  => [ 'wpml-node-modules', 'wp-i18n', 'lodash' ],
      ],
    ],

    'styles'                         => [
      [
        'id'           => 'wpml-dashboard',
        'src'          => 'public/css/dashboard.css',
        'dependencies' => [ 'otgs-icons' ],
      ],
      [
        'id'           => 'wpml-translations-tabs',
        'src'          => 'public/css/tailwind.css',
        'dependencies' => [],
      ],
    ],

    'endpoints'                      => [
      'getpopulateditemsections'       => [
        'path'    => '/item-sections/populated',
        'handler' => GetPopulatedItemSectionsController::class,
      ],
      'getposts'                       => [
        'path'    => '/posts',
        'handler' => GetPostControllerInterface::class,
      ],
      'getpostscount'                  => [
        'path'    => '/posts/count',
        'handler' => GetPostsCountController::class,
      ],
      'sendtotranslation'              => [
        'path'    => '/send-to-translation',
        'handler' => SendToTranslationController::class,
        'method'  => 'POST',
        'useAjax' => true,
        'args'    => [
          'batchName'          => [ 'type' => 'string' ],
          'sourceLanguageCode' => [ 'type' => 'string' ],
        ],
      ],
      'validatetranslationoptions'     => [
        'path'    => '/send-to-translation/validate-translation-options',
        'handler' => ValidateSelectedTranslationMethodsController::class,
        'method'  => 'POST',
        'args'    => [
          'batchName'          => [ 'type' => 'string' ],
          'sourceLanguageCode' => [ 'type' => 'string' ],
        ],
      ],
      'getdefaultbatchname'            => [
        'path'    => '/send-to-translation/default-batch-name',
        'handler' => GetTranslationBatchDefaultName::class,
        'method'  => 'GET',
      ],
      'validatebatchname'              => [
        'path'    => '/send-to-translation/validate-batch-name',
        'handler' => ValidateTranslationBatchNameController::class,
        'method'  => 'POST',
        'args'    => [
          'batchName' => [ 'type' => 'string' ],
        ],
      ],
      'setreviewtranslationoption'     => [
        'path'    => '/set-review-translation-option',
        'handler' => SetReviewTranslationOptionController::class,
        'method'  => 'POST',
        'args'    => [
          'reviewOption' => [ 'type' => 'string' ],
        ],
      ],
      'gethierarchicalposts'           => [
        'path'    => '/posts/hierarchical',
        'handler' => GetHierarchicaPostsController::class,
        'args'    => [
          'type'               => [ 'type' => 'string' ],
          'sourceLanguageCode' => [ 'type' => 'string' ],
          'search'             => [ 'type' => 'string' ],
          'limit'              => [ 'type' => 'integer' ],
          'offset'             => [ 'type' => 'integer' ],
        ],
      ],
      'getposttaxonomies'              => [
        'path'    => '/posts/taxonomies',
        'handler' => GetPostTaxonomiesController::class,
        'args'    => [
          'sourceLanguageCode' => [ 'type' => 'string' ],
        ],
      ],
      'getpostterms'                   => [
        'path'    => '/posts/terms',
        'handler' => GetPostTermsController::class,
        'args'    => [
          'taxonomyId'         => [ 'type' => 'string' ],
          'sourceLanguageCode' => [ 'type' => 'string' ],
          'search'             => [ 'type' => 'string' ],
          'limit'              => [ 'type' => 'integer' ],
          'offset'             => [ 'type' => 'integer' ],
        ],
      ],
      'enabletranslateeverything'      => [
        'path'    => '/translate-everything/enable',
        'handler' => EnableController::class,
        'method'  => 'POST',
      ],
      'disabletranslateeverything'     => [
        'path'    => '/translate-everything/disable',
        'handler' => DisableController::class,
        'method'  => 'POST',
      ],
      'rechecktranslateeverythingreachability' => [
        'path'    => '/translate-everything/recheck-reachability',
        'handler' => RecheckReachabilityController::class,
        'method'  => 'POST',
      ],
      'cancelallautomaticjobs'     => [
        'path'    => '/cancelallautomaticjobs',
        'handler' => CancelAllAutomaticJobsController::class,
        'method'  => 'GET',
      ],
      'getautomaticjobsinprogress' => [
        'path'    => '/getautomaticjobsinprogress',
        'handler' => GetAutomaticJobsInProgressController::class,
        'method'  => 'GET',
      ],

      'getuntranslatedtypescount'      => [
        'path'    => '/getuntranslatedtypescount',
        'handler' => GetUntranslatedTypesCountController::class,
        'method'  => 'GET',
      ],
      'savetranslatornote'             => [
        'path'    => '/save-translator-note',
        'handler' => SaveTranslatorNoteController::class,
        'method'  => 'POST',
      ],
      'committotranslationproxy'       => [
        'path'    => '/translation-proxy/commit-batch',
        'handler' => SendCommitRequestController::class,
        'method'  => 'POST',
      ],
      'getlastpickedup'                => [
        'path'    => '/tranlsation-proxy/getlastpickedup',
        'handler' => GetLastPickedUpController::class,
        'method'  => 'GET',
      ],
      'getremotejobscount'             => [
        'path'    => '/translation-proxy/getRemoteJobsCount',
        'handler' => GetRemoteJobsCountController::class,
        'method'  => 'GET',
      ],
      'gettranslationstatus'           => [
        'path'    => '/gettranslationstatus',
        'handler' => GetTranslationStatusController::class,
        'method'  => 'GET',
      ],
      'getlocaltranslatorbyid'         => [
        'path'    => '/getlocaltranslatorbyid',
        'handler' => GetTranslatorByIdController::class,
        'method'  => 'GET',
        'args'    => [
          'translatorId' => [ 'type' => 'integer' ],
        ],
      ],
      'reloadremotetranslationservice' => [
        'path'    => '/reloadremotetranslationservice',
        'handler' => GetRemoteTranslationServiceController::class,
        'method'  => 'GET',
      ],
      'gettranslationeditortype'       => [
        'path'    => '/gettranslationeditortype',
        'handler' => GetTranslationEditorTypeController::class,
        'method'  => 'GET',
      ],

      'translateexistingcontent'        => [
        'path'    => '/translate-existing-content',
        'handler' => TranslateExistingContentController::class,
        'method'  => 'POST',
      ],
      'getneedsupdatecountcreatedincte' => [
        'path'    => '/get-needs-update-count-created-in-cte',
        'handler' => GetNeedsUpdateCreatedInCteController::class,
        'method'  => 'GET',
      ],
      'getengines'                      => [
        'path'    => '/get-engines',
        'handler' => GetEnginesController::class,
        'method'  => 'GET',
      ],
      'getwebsitecontext'                      => [
        'path'    => '/website-contexts',
        'handler' => GetWebsiteContextController::class,
        'method'  => 'GET',
      ],
      'haspostsusingnativeeditor'       => [
        'path'    => '/has-posts-using-native-editor',
        'handler' => HasPostsUsingNativeEditorController::class,
        'method'  => 'GET',
      ],
      'getcreditsperword' => [
        'path'    => '/get-credits-per-word',
        'handler' => GetCreditsPerWordController::class,
        'method'  => 'GET',
      ],
      'getwordstotranslateforitems' => [
        'path'    => '/get-words-to-translate-for-items',
        'handler' => GetWordsToTranslateForItemsController::class,
        'method'  => 'POST',
      ],
      'getwordstotranslatefortypes' => [
        'path'    => '/get-words-to-translate-for-types',
        'handler' => GetWordsToTranslateForTypesController::class,
        'method'  => 'POST',
      ],
    ],
  ],
  'automatic-translations-settings'           => [
    'requiresWPMLSetupToBeCompleted' => true,

    'endpoints' => [
      'getengines'                       => [
        'path'    => '/get-engines',
        'handler' => GetEnginesController::class,
        'method'  => 'GET',
      ],
      'saveautomatictranslationsettings' => [
        'path'    => '/save-automatic-translation-settings',
        'handler' => SaveAutomaticTranslationsSettingsController::class,
        'method'  => 'POST',
      ],
      'getactiveengine'                  => [
        'path'    => '/get-active-engine',
        'handler' => AiTranslationActiveEngineController::class,
        'method'  => 'GET',
      ],
    ],
  ],
  'sitepress-multilingual-cms/menu/setup.php' => [
    /* translators: Title of the WPML setup wizard screen. */
    'title'                          => __( 'WPML Setup', 'wpml' ),
    'requiresWPMLSetupToBeCompleted' => false,
    'dependencies'                   => [
      'wpml-node-modules',
      'wp-i18n',
      'lodash'
    ],

    'legacyExtension' => 'load-sitepress-multilingual-cms/menu/setup.php',

    'scripts' => [
      [
        'id'           => 'wpml-wc-minimum-requirements',
        'src'          => 'public/js/wc-minimum-requirements.js',
        'dependencies' => [ 'wpml-node-modules', 'wp-i18n', 'lodash' ]
      ],
      [
        'id'           => 'wpml-wc-minimum-requirements-warning-banner',
        'src'          => 'public/js/wc-minimum-requirements-warning-banner.js',
        'dependencies' => [ 'wpml-node-modules', 'wp-i18n', 'lodash' ]
      ],
    ],

    'styles' => [
      'src'          => 'public/css/tailwind.css',
      'dependencies' => []
    ]
  ],
  'sitepress-multilingual-cms/menu/support.php' => [
    'controller'                     => SupportSubPageDispatcher::class,
    /* translators: Name of the WPML Support screen: the admin menu item, the page heading, and the back-link that returns to it. Noun (help from the WPML support team), not the verb "to support". */
    'title'                          => __( 'Support', 'wpml' ),
    /* translators: Name of the WPML Support screen: the admin menu item, the page heading, and the back-link that returns to it. Noun (help from the WPML support team), not the verb "to support". */
    'menuTitle'                      => __( 'Support', 'wpml' ),
    'capability'                     => 'wpml_manage_support',
    'legacyParentId'                 => 'WPML',
    'position'                       => 4,
    'requiresWPMLSetupToBeCompleted' => false,
    'dependencies'                   => [
      'wpml-node-modules',
      'wp-i18n',
      'lodash',
    ],
    'scripts'                        => [
      [
        'id'           => 'wpml-wc-minimum-requirements',
        'src'          => 'public/js/wc-minimum-requirements.js',
        'dependencies' => [ 'wpml-node-modules', 'wp-i18n', 'lodash' ],
      ],
      [
        'id'           => 'wpml-wc-minimum-requirements-warning-banner',
        'src'          => 'public/js/wc-minimum-requirements-warning-banner.js',
        'dependencies' => [ 'wpml-node-modules', 'wp-i18n', 'lodash' ],
      ],
      [
        'id'            => 'wpml-support-alias-domains',
        'src'           => 'public/js/wpml-troubleshooting.js',
        'dependencies'  => [ 'wpml-node-modules', 'wp-i18n', 'lodash' ],
        'prerequisites' => AliasDomainsController::class,
        'dataProvider'  => AliasDomainsController::class,
      ],
    ],
    'styles'                         => [
      [
        'id'           => 'wpml-support-tailwind',
        'src'          => 'public/css/tailwind.css',
        'dependencies' => [],
      ],
      [
        'id'           => 'wpml-support-alias-domains',
        'src'          => 'public/css/wpml-troubleshooting.css',
        'dependencies' => [],
      ],
    ],
    'endpoints'                      => [
      'createpluginreport' => [
        'path'       => '/support/plugin-report',
        'method'     => MethodType::POST,
        'handler'    => CreatePluginReportController::class,
        'capability' => [ 'wpml_manage_support', WPML_CAP_MANAGE_TRANSLATIONS ],
      ],
      'activaterepairtools' => [
        'path'       => '/support/activate-repair-tools',
        'method'     => MethodType::POST,
        'handler'    => ActivateRepairToolsController::class,
        'capability' => 'activate_plugins',
      ],
      'updateposthogstate' => [
        'path'       => '/troubleshooting/posthog',
        'method'     => MethodType::POST,
        'handler'    => UpdatePostHogStateController::class,
        'capability' => 'wpml_manage_support',
      ],
      'clearremotexmlconfiglog' => [
        'path'       => '/troubleshooting/remote-xml-config-log/clear',
        'method'     => MethodType::POST,
        'handler'    => ClearRemoteXMLConfigLogController::class,
        'capability' => 'wpml_manage_support',
      ],
      'enablealiasdomain' => [
        'path'       => '/support/enable-alias-domain',
        'method'     => MethodType::POST,
        'handler'    => EnableAliasDomainController::class,
        'capability' => 'wpml_manage_support',
      ],
      'registeraliasdomain' => [
        'path'       => '/support/register-alias-domain',
        'method'     => MethodType::POST,
        'handler'    => RegisterAliasDomainController::class,
        'capability' => 'wpml_manage_support',
      ],
      'resetaliasdomain' => [
        'path'       => '/support/reset-alias-domain',
        'method'     => MethodType::POST,
        'handler'    => ResetAliasDomainController::class,
        'capability' => 'wpml_manage_support',
      ],
    ],
  ],


  'tm/menu/settings'                          => [
    'controller'                     => SettingsSectionDispatcher::class,
    /* translators: Name of the WPML Settings screen: the admin menu item, the page heading, and the back-link that returns to it. */
    'title'                          => __( 'Settings', 'wpml' ),
    /* translators: Name of the WPML Settings screen: the admin menu item, the page heading, and the back-link that returns to it. */
    'menuTitle'                      => __( 'Settings', 'wpml' ),
    'capability'                     => 'manage_translations',
    'legacyParentId'                 => 'WPML',
    'position'                       => 2,
    'requiresWPMLSetupToBeCompleted' => true,
    'scripts'                        => [
      [
        'id'           => 'wpml-settings',
        'src'          => 'public/js/wpml-settings.js',
        'dataProvider' => SettingsSectionDispatcher::class,
        'dependencies' => [ 'wpml-node-modules', 'wp-i18n', 'lodash' ],
      ],
    ],
    'styles'                         => [
      'src' => 'public/css/tailwind.css',
    ],
  ],

  'wpml-ai-translation-billing'               => [
    'controller'                     => BillingController::class,
    /* translators: Name of the WPML billing screen: the admin menu item and the page heading. */
    'title'                          => __( 'AI Translation: billing and usage', 'wpml' ),
    /* translators: Name of the WPML billing screen: the admin menu item and the page heading. */
    'menuTitle'                      => __( 'AI Translation: billing and usage', 'wpml' ),
    'capability'                     => 'manage_translations',
    'legacyParentId'                 => 'WPML',
    'position'                       => 3,
    'requirements'                   => BillingPageRequirements::class,
    'requiresWPMLSetupToBeCompleted' => true,
    'styles'                         => [
      'src'          => 'public/css/tailwind.css',
      'dependencies' => [],
    ],
  ],

  'wpml-activate-update'                      => [
    'controller'                     => ActivateUpdateController::class,
    /* translators: Name of the WPML Activate & Update screen: the admin menu item and the page heading. */
    'title'                          => __( 'Activate & Update', 'wpml' ),
    /* translators: Name of the WPML Activate & Update screen: the admin menu item and the page heading. */
    'menuTitle'                      => __( 'Activate & Update', 'wpml' ),
    'capability'                     => WPML_CAP_MANAGE_OPTIONS,
    'legacyParentId'                 => 'WPML',
    'position'                       => 5,
    'requiresWPMLSetupToBeCompleted' => false,
  ],

  AdminTextsTranslationController::PAGE_SLUG  => [
    'controller'                     => AdminTextsTranslationController::class,
    'title'                          => __( 'Admin Texts Translation', 'wpml' ),
    'menuTitle'                      => __( 'Admin Texts Translation', 'wpml' ),
    'parentId'                       => 'wpml-translations-hidden',
    'styles'                         => [
      'src'          => 'public/css/tailwind.css',
      'dependencies' => [],
    ],
    'requiresWPMLSetupToBeCompleted' => true,
  ],
];
