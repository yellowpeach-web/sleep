<?php

namespace WPML\UserInterface\Web\Core\Component\Dashboard\Application;

use WPML\Core\SharedKernel\Component\ATE\Application\Repository\AteReachabilityRepositoryInterface;
use WPML\Core\SharedKernel\Component\ATE\Application\Repository\ClientRestrictionRepositoryInterface;
use WPML\Core\SharedKernel\Component\ATE\Application\Repository\SpendCapRepositoryInterface;
use WPML\Core\Component\Post\Application\Query\Dto\PublicationStatusDto;
use WPML\Core\Component\Post\Application\Query\PublicationStatusQueryInterface;
use WPML\Core\Component\Translation\Application\Query\JobQueryInterface;
use WPML\Core\Component\Translation\Application\Query\TranslationBatchesQueryInterface;
use WPML\Core\Component\Translation\Application\Repository\SettingsRepository;
use WPML\Core\Component\TranslationProxy\Application\Query\RemoteJobsQueryInterface;
use WPML\Core\Component\TranslationProxy\Application\Service\LastPickedUpDateServiceInterface;
use WPML\Core\Component\TranslationProxy\Application\Service\RemoteTranslationService;
use WPML\Core\Component\TranslationProxy\Application\Service\TranslationProxyServiceInterface;
use WPML\Core\SharedKernel\Component\TranslationProxy\Domain\Query\FetchRemoteTranslationServiceException;
use WPML\Core\SharedKernel\Component\Translator\Application\Service\Dto\TranslatorDto;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\Port\PluginInterface;
use WPML\Core\SharedKernel\Component\Language\Application\Query\Dto\LanguageDto;
use WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface;
use WPML\Core\SharedKernel\Component\ATE\Application\Query\SiteIDQueryInterface;
use WPML\Core\SharedKernel\Component\Installer\Application\Query\WpmlSiteKeyQueryInterface;
use WPML\Core\SharedKernel\Component\Post\Application\Query\Dto\PostTypeDto;
use WPML\Core\SharedKernel\Component\Site\Application\Query\SiteMigrationLockQueryInterface;
use WPML\Core\SharedKernel\Component\Translator\Application\Service\TranslatorsService;
use WPML\Core\SharedKernel\Component\User\Application\Query\UserQueryInterface;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetPopulatedItemSections\GetPopulatedItemSectionsController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetTranslationBatchDefaultName\GetTranslationBatchDefaultName;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Hook\DashboardItemSectionsFilterInterface;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Hook\DashboardPublicationStatusFilterInterface;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Query\DashboardTranslatableTypesQueryInterface;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\ViewModel\ItemSection;
use WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application\TeaUpgradeNoticeDataProvider;
use WPML\UserInterface\Web\Core\Component\Preferences\Application\LanguagePreferencesLoader;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Service\ParkedTypesService;
use WPML\UserInterface\Web\Core\Port\Script\ScriptDataProviderInterface;
use WPML\UserInterface\Web\Core\Port\Script\ScriptPrerequisitesInterface;
use WPML\UserInterface\Web\Infrastructure\WordPress\Endpoint\StringItemEndpointData;

class DashboardController implements
  ScriptPrerequisitesInterface,
  ScriptDataProviderInterface {

  private $translatableItems;

  private $jobQuery;

  private $publicationStatusQuery;

  private $dashboardItemSectionsFilter;

  private $dashboardPublicationStatusFilter;

  private $translatorsService;

  private $stringItemEndpointData;

  private $getTranslationBatchDefaultName;

  private $translationSettingsRepository;

  private $remoteTranslationServiceService;

  private $lastPickedUpDateService;

  private $remoteJobsQuery;

  private $translationProxyService;

  private $userQuery;

  private $languagePreferencesLoader;

  private $translationBatchesQuery;

  private $plugin;

  private $ateReachabilityRepository;

  private $clientRestrictionRepository;

  private $spendCapRepository;
  private $teaUpgradeNoticeDataProvider;

  private $siteKeyQuery;

  private $siteIdQuery;

  private $populatedItemSectionsEndpoint;

  private $parkedTypesService;

  private $siteMigrationLockQuery;

  public function __construct(
    DashboardTranslatableTypesQueryInterface $translatableItems,
    JobQueryInterface $jobQuery,
    PublicationStatusQueryInterface $publicationStatusQuery,
    TranslatorsService $translatorsService,
    DashboardPublicationStatusFilterInterface $dashboardPublicationStatusFilter,
    DashboardItemSectionsFilterInterface $dashboardItemSectionsFilter,
    StringItemEndpointData $stringItemEndpointData,
    GetTranslationBatchDefaultName $getTranslationBatchDefaultName,
    SettingsRepository $translationSettingsRepository,
    RemoteTranslationService $remoteTranslationServiceService,
    LastPickedUpDateServiceInterface $lastPickedUpDateService,
    RemoteJobsQueryInterface $remoteJobsQuery,
    TranslationProxyServiceInterface $translationProxyService,
    UserQueryInterface $userQuery,
    LanguagePreferencesLoader $languagePreferencesLoader,
    TranslationBatchesQueryInterface $translationBatchesQuery,
    PluginInterface $plugin,
    AteReachabilityRepositoryInterface $ateReachabilityRepository,
    ClientRestrictionRepositoryInterface $clientRestrictionRepository,
    SpendCapRepositoryInterface $spendCapRepository,
    TeaUpgradeNoticeDataProvider $teaUpgradeNoticeDataProvider,
    WpmlSiteKeyQueryInterface $siteKeyQuery,
    GetPopulatedItemSectionsController $populatedItemSectionsEndpoint,
    ParkedTypesService $parkedTypesService,
    SiteMigrationLockQueryInterface $siteMigrationLockQuery,
    SiteIDQueryInterface $siteIdQuery
  ) {
    $this->translatableItems                = $translatableItems;
    $this->jobQuery                         = $jobQuery;
    $this->publicationStatusQuery           = $publicationStatusQuery;
    $this->dashboardPublicationStatusFilter = $dashboardPublicationStatusFilter;
    $this->dashboardItemSectionsFilter      = $dashboardItemSectionsFilter;
    $this->translatorsService               = $translatorsService;
    $this->stringItemEndpointData           = $stringItemEndpointData;
    $this->getTranslationBatchDefaultName   = $getTranslationBatchDefaultName;
    $this->translationSettingsRepository    = $translationSettingsRepository;
    $this->remoteTranslationServiceService  = $remoteTranslationServiceService;
    $this->lastPickedUpDateService          = $lastPickedUpDateService;
    $this->remoteJobsQuery                  = $remoteJobsQuery;
    $this->translationProxyService          = $translationProxyService;
    $this->userQuery                        = $userQuery;
    $this->languagePreferencesLoader        = $languagePreferencesLoader;
    $this->translationBatchesQuery          = $translationBatchesQuery;
    $this->plugin                           = $plugin;
    $this->ateReachabilityRepository        = $ateReachabilityRepository;
    $this->clientRestrictionRepository      = $clientRestrictionRepository;
    $this->spendCapRepository               = $spendCapRepository;
    $this->teaUpgradeNoticeDataProvider     = $teaUpgradeNoticeDataProvider;
    $this->siteKeyQuery                     = $siteKeyQuery;
    $this->siteIdQuery                      = $siteIdQuery;
    $this->populatedItemSectionsEndpoint    = $populatedItemSectionsEndpoint;
    $this->parkedTypesService               = $parkedTypesService;
    $this->siteMigrationLockQuery           = $siteMigrationLockQuery;
  }


  public function jsWindowKey(): string {
    return 'wpmlScriptData';
  }


  private function getRemoteTranslationServiceData() {
    try {
      $remoteTranslationService = $this->remoteTranslationServiceService->getCurrent();

      if ( ! $remoteTranslationService ) {
        return null;
      }

      return $remoteTranslationService->toArray();
    } catch ( FetchRemoteTranslationServiceException $e ) {
      return null;
    }
  }


  private function getDashboardUrls(): array {
    return [
      'amsBaseUrl'                      => $this->plugin->getAMSHost(),
      'translatorspage'                 => admin_url( 'admin.php?page=tm%2Fmenu%2Fsettings&section=translators' ),
      'jobs'                            => admin_url( 'admin.php?page=tm%2Fmenu%2Fmain.php&tab=jobs' ),
      'translationqueue'                => admin_url( 'admin.php?page=tm%2Fmenu%2Fmain.php&tab=tasks' ),
      'automaticTranslationSettingsTab' => admin_url( 'admin.php?page=wpml-ai-translation-billing' ),
      'wpmlSettingsTranslationEditor'   => admin_url( 'admin.php?page=tm%2Fmenu%2Fsettings&section=translation-editor' ),
      'themeAndLocalisationPage'        => admin_url( 'admin.php?page=tm%2Fmenu%2Fsettings&section=compatibility' ),
      'adminTextsPage'                  => admin_url( 'admin.php?page=wpml-admin-texts-translation' ),
      'stringTranslationPage'           => admin_url( 'admin.php?page=tm%2Fmenu%2Fmain.php&tab=strings' ),
      'languageEditorPage'              => admin_url( 'admin.php?page=sitepress-multilingual-cms%2Fmenu%2Flanguages.php&trop=1' ),
      'glossaryPage'                    => admin_url( 'admin.php?page=tm%2Fmenu%2Fmain.php&tab=glossary' ),
      'connectedSites'                  => admin_url( 'admin.php?page=wpml-ai-translation-billing&settings=connected_sites' ),
      'translationProxyUrl'             => $this->translationProxyService->getTPUrl(),
      'ledgerUrl'                       => apply_filters( 'wpml_release_ledger_url', '' ),
      'translationEngine'               => admin_url( 'admin.php?page=tm%2Fmenu%2Fsettings&section=ai-translation' ),
    ];
  }


  private function getTranslatableItems(): array {
    $translatableItems = array_map( function ( PostTypeDto $itemType ): ItemSection {
      return ItemSection::createFromPostType( $itemType );
    }, $this->translatableItems->getTranslatable() );

    $filteredSections = $this->dashboardItemSectionsFilter->filter( $translatableItems );

    return array_map(
      function ( ItemSection $itemSection ) {
        return $itemSection->toArray();
      },
      $filteredSections
    );
  }


  private function getFilteredPublicationStatuses(): array {
    return $this->dashboardPublicationStatusFilter->filterByDto(
      $this->publicationStatusQuery->getNotInternalStatuses()
    );
  }


  private function getPublicationStatuses(): array {
    $filteredPublicationStatuses = $this->getFilteredPublicationStatuses();

    return array_map( function ( PublicationStatusDto $status ) {
      return $status->toArray();
    }, $filteredPublicationStatuses );
  }


  private function getTranslators(): array {
    return array_map(
      function ( TranslatorDto $translatorDto ) {
        return $translatorDto->toArray();
      },
      $this->translatorsService->get()
    );
  }


  private function getCurrentlyLoggedInTranslator() {
    $currentlyLoggedInTranslator = $this->translatorsService->getCurrentlyLoggedId();

    return $currentlyLoggedInTranslator ? $currentlyLoggedInTranslator->toArray() : null;
  }



  private function getReviewOption() {
    $reviewOption = $this->translationSettingsRepository
      ->getSettings()
      ->getReviewMode();

    return $reviewOption ? $reviewOption->getValue() : null;
  }


  private function getStringsSections(): array {
    if ( ! $this->stringItemEndpointData->isStPluginActive() ) {
      return [];
    }

    return [
      [
        'id'       => 'string',
        'title'    => __( 'Other texts (Strings)', 'wpml' ),
        'singular' => __( 'Other texts (Strings)', 'wpml' ),
        'plural'   => __( 'Other texts (Strings)', 'wpml' ),
        'kind'     => [
          'id' => 'string',
        ]
      ]
    ];
  }


  public function scriptPrerequisitesMet(): bool {
    return ! array_key_exists( 'sm', $_GET ) || $_GET['sm'] === 'dashboard';
  }

  public function initialScriptData(): array {
    $currentUser               = $this->userQuery->getCurrent();
    $currentTranslationService = $this->getRemoteTranslationServiceData();

    $translateEverythingSettings = $this->translationSettingsRepository
      ->getSettings()
      ->getTranslateEverything();

    $itemSections           = array_merge(
      $this->getTranslatableItems(),
      $this->getStringsSections()
    );
    $itemSections           = $this->dashboardItemSectionsFilter->addNoteToSections( $itemSections );
    $populatedItemSections  = $this->getPopulatedItemSections( $itemSections );
    $predefinedStringDomain = $this->getPredefinedStringDomain( $itemSections );

    $defaultPopulatedItemSections = null;
    $initialPopulatedItemSections = null;
    try {
      $allSectionIds = array_map(
        function ( $itemSection ) {
          return $itemSection['id'];
        },
        $itemSections
      );
      $sourceCode = $this->languagePreferencesLoader->get()['languagesSettings']['from'];

      $defaultPopulatedItemSections = $this->populatedItemSectionsEndpoint->handle(
        [
          'itemSectionIds'     => $allSectionIds,
          'sourceLanguageCode' => $sourceCode,
        ]
      )['itemSectionIds'];

      $initialPopulatedItemSections = $this->populatedItemSectionsEndpoint->handle(
        [
          'itemSectionIds'      => $allSectionIds,
          'sourceLanguageCode'  => $sourceCode,
          'translationStatuses' => self::initialTranslationStatuses(),
        ]
      )['itemSectionIds'];
    } catch ( \WPML\PHP\Exception\Exception $e ) {
      $defaultPopulatedItemSections = null;
      $initialPopulatedItemSections = null;
    }

    $translationEditor = $this->translationSettingsRepository
      ->getSettings()
      ->getTranslationEditor();

    $isAteEnabled                           = $translationEditor && $translationEditor->getValue() === 'ATE';
    $useAteForOldTranslationsCreatedWithCte = $isAteEnabled && $translationEditor->useAteForOldTranslationsCreatedWithCte();

    $isAteReachable = $this->ateReachabilityRepository->get();
    if ( $this->siteMigrationLockQuery->isLocked() ) {
      $isAteReachable = false;
    }

    $languageData = $this->languagePreferencesLoader->get();

    $otherData = [
      'currentUser' => $currentUser ? $currentUser->toArray() : null,

      'siteKey' => $this->siteKeyQuery->get() ?: '',

      'ateSiteId' => $this->siteIdQuery->get() ?: '',

      'wpmlStartVersion' => $this->plugin->getVersionWhenSetupRan(),

      // Only when tm.php loaded: a Blog license defines WPML_TM_URL too
      'legacyResourceUrl' => defined( 'WPML_TM_VERSION' ) && defined( 'WPML_TM_URL' ) ? WPML_TM_URL : '',

      'urls' => $this->getDashboardUrls(),

      'itemSections'           => $itemSections,
      'populatedItemSections'  => $populatedItemSections,
      'defaultPopulatedItemSections' => $defaultPopulatedItemSections,
      'initialPopulatedItemSections' => $initialPopulatedItemSections,
      'predefinedStringDomain' => $predefinedStringDomain,

      'translateEverything' => [
        'isEnabled'                   => $translateEverythingSettings->isEnabled(),
        'hasAnyAutomaticTranslations' => $this->jobQuery->hasAnyAutomatic(),
        'automaticJobsInProgressCount' => $this->jobQuery->countAutomaticInProgress(),
        'chargedJobsInProgress'        => $this->jobQuery->getInFlightChargedAutomatic(),
        'needsReviewCount'             => $this->jobQuery->countNeedsReview(),
        'hasEverBeenEnabled'          => $translateEverythingSettings->hasEverBeenEnabled(),
        'needsReviewJobsBatchType'    => $this->translationBatchesQuery->getNeedsReviewJobsBatchType(),
        'completedPosts'              => $translateEverythingSettings->getCompletedPosts(),
        'completedPackages'           => $translateEverythingSettings->getCompletedPackages(),
        'completedStrings'            => $translateEverythingSettings->getCompletedStrings(),
        'parkedTypes'                 => $this->parkedTypesService->getParkedTypesOffer(),
      ],

      'isAiSkipped'                            => $this->plugin->isAiSetupSkipped(),
      'clientRestriction'                      => $this->clientRestrictionRepository->get(),
      'spendCap'                               => $this->spendCapRepository->get(),
      'isAteReachable'                         => $isAteReachable,
      'publicationStatuses'                    => $this->getPublicationStatuses(),
      'translators'                            => $this->getTranslators(),
      'currentlyLoggedInTranslator'            => $this->getCurrentlyLoggedInTranslator(),
      'translationBatchDefaultName'            => $this->getTranslationBatchDefaultName->handle()['value'],
      'reviewTranslationOption'                => $this->getReviewOption(),
      'stApi'                                  => $this->stringItemEndpointData->getEndpointData(),
      'stPackagesApi'                          => $this->stringItemEndpointData->getStringPackagesEndpointData(),
      'remoteTranslationService'               => $currentTranslationService,
      'isAteEnabled'                           => $isAteEnabled,
      'useAteForOldTranslationsCreatedWithCte' => $useAteForOldTranslationsCreatedWithCte,
      'useNativeEditorForAllPostTypes'         => $translationEditor && $translationEditor->useNativeEditorForAllPostTypes(),
      'postTypesUsingNativeEditor'             => $translationEditor ? $translationEditor->getPostTypesUsingNativeEditor() : [],
      'translationProxyLastPickedUp'           => $this->lastPickedUpDateService->get(),
      'remoteJobsCount'                        => ! isset( $currentTranslationService ) ?
        0 :
        $this->remoteJobsQuery->getCount( $currentTranslationService['id'] ),
      'translationProxyDebugModeEnabled'       => defined( 'WPML_POLLING_BOX_DEBUG_MODE' ) &&
                                                  WPML_POLLING_BOX_DEBUG_MODE,
      'addOns'                                 => [ 'ST' => $this->stringItemEndpointData->isStPluginActive() ],
      'stAutoregistration'                     => $this->stringItemEndpointData->getAutoregistrationData(),
      'teaUpgradeNotice'                       => $this->getTeaUpgradeNoticeData(),
    ];

    $preflight           = apply_filters( 'wpml_translate_everything_preflight_advisories', [] );
    $preflightAdvisories = is_array( $preflight ) ? $preflight : [];
    if ( $preflightAdvisories ) {
      $otherData['teaPreflightAdvisories'] = $preflightAdvisories;
    }

    return apply_filters(
      'wpml_dashboard_initial_script_data',
      array_merge( $languageData, $otherData )
    );
  }


  private function getTeaUpgradeNoticeData(): array {
    $data = $this->teaUpgradeNoticeDataProvider->get();

    $data['siteKey'] = $this->siteKeyQuery->get() ?: '';

    return $data;
  }

  private function getPopulatedItemSections( array $itemSections ) {
    $itemSectionsArgs      = filter_input( INPUT_GET, 'sections', FILTER_SANITIZE_SPECIAL_CHARS );
    $populatedItemSections = explode( ',', $itemSectionsArgs ?: '' );

    $sectionIds = array_map( function ( $itemSection ) {
      return $itemSection['id'];
    }, $itemSections );

    $populatedItemSections = array_values( array_filter( $populatedItemSections, function ( $section ) use ( $sectionIds ) {
      return in_array( $section, $sectionIds );
    } ) );

    return count( $populatedItemSections ) ? $populatedItemSections : null;
  }


  private function getPredefinedStringDomain( array $itemSections ): string {
    if ( ! in_array( 'string', $this->getPopulatedItemSections( $itemSections ) ?: [] ) ) {
      return '';
    }

    $predefinedStringDomainArg = filter_input( INPUT_GET, 'predefinedStringDomain', FILTER_SANITIZE_SPECIAL_CHARS );

    return $predefinedStringDomainArg ?: '';
  }


  private static function initialTranslationStatuses(): array {
    return [ 0, 3, 30, 1, 2, 41, 40 ];
  }


}
