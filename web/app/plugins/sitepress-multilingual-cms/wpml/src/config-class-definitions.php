<?php




use WPML\Core\Component\MinimumRequirements\Application\Repository\RequirementsRefreshLockInterface;
use WPML\Core\Component\MinimumRequirements\Application\Service\RequirementsService;
use WPML\Core\Component\MinimumRequirements\Domain\Entity\DatabaseVersionRequirement;
use WPML\Core\Component\MinimumRequirements\Domain\Entity\EvalFunctionRequirement;
use WPML\Core\Component\MinimumRequirements\Domain\Entity\LibXmlVersionRequirement;
use WPML\Core\Component\MinimumRequirements\Domain\Entity\MbstringExtensionRequirement;
use WPML\Core\Component\MinimumRequirements\Domain\Entity\MemoryLimitRequirement;
use WPML\Core\Component\MinimumRequirements\Domain\Entity\PHPVersionRequirement;
use WPML\Core\Component\MinimumRequirements\Domain\Entity\RestEnabledRequirement;
use WPML\Core\Component\MinimumRequirements\Domain\Entity\SimpleXMLExtensionRequirement;
use WPML\Core\Component\MinimumRequirements\Domain\Entity\StackSizeRequirement;
use WPML\Core\Component\MinimumRequirements\Domain\Entity\WordPressVersionRequirement;
use WPML\Core\Component\Post\Domain\WordCount\ItemContentCalculator\PostCalculator;
use WPML\Core\Component\Post\Domain\WordCount\ItemContentCalculator\PostContentFilterInterface;
use WPML\Core\Component\Translation\Application\Query\NewestJobsForPairsQueryInterface;
use WPML\Core\Component\Translation\Application\Query\Priority\PostDataQueryInterface;
use WPML\Core\Component\Translation\Application\Query\Priority\SiteSettingsQueryInterface;
use WPML\Core\Component\Translation\Application\Query\Priority\StringDataQueryInterface;
use WPML\Core\Component\Translation\Application\Query\Priority\TermDataQueryInterface;
use WPML\Core\Component\Translation\Application\Service\Priority\ClassificationContextBuilder;
use WPML\Core\Component\Translation\Application\Service\Priority\JobPriorityService;
use WPML\Core\Component\Translation\Application\Service\TranslationService;
use WPML\Core\Component\Translation\Application\Service\TranslatorNoteService;
use WPML\Core\Component\Translation\Application\String\Repository\StringBatchRepositoryInterface;
use WPML\Core\Component\Translation\Application\String\StringBatchBuilder;
use WPML\Core\Component\Translation\Domain\CompletedTranslationDetector;
use WPML\Core\Component\Translation\Domain\TranslationBatch\Validator\CompletedTranslationValidator;
use WPML\Core\Component\Translation\Domain\TranslationBatch\Validator\CompositeValidator;
use WPML\Core\Component\Translation\Domain\TranslationBatch\Validator\ElementTargetLanguageValidator;
use WPML\Core\Component\Translation\Domain\TranslationBatch\Validator\EmptyMethodsValidator;
use WPML\Core\Component\Translation\Domain\TranslationBatch\Validator\ValidatorInterface;
use WPML\Core\Component\WordsToTranslate\Domain\Calculator\WordsToTranslate;
use WPML\Core\Component\WordsToTranslate\Domain\Post\Query\JobQueryInterface as PostJobQueryInterface;
use WPML\Core\Component\WordsToTranslate\Domain\Post\Query\PostQueryInterface;
use WPML\Core\Port\PluginInterface;
use WPML\Core\SharedKernel\Component\Item\Application\Service\UntranslatedService;
use WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface;
use WPML\Core\SharedKernel\Component\Setting\Application\Service\TranslationEditorService;
use WPML\Core\SharedKernel\Component\User\Application\Query\UserQueryInterface;
use WPML\DicInterface;
use WPML\Infrastructure\WordPress\Component\Item\Application\Query\UntranslatedTypesCountBeforeSetupQuery;
use WPML\Infrastructure\WordPress\SharedKernel\Server\Application\CheckRestIsEnabled;
use WPML\Infrastructure\WordPress\SharedKernel\Server\Application\WordPressOptionCache;
use WPML\Infrastructure\WordPress\Component\Item\Application\Query\UntranslatedTypesCountQuery as PostUntranslatedTypesCountQuery;
use WPML\Infrastructure\WordPress\Component\String\Application\Query\UntranslatedTypesCountQuery as StringUntranslatedTypesCountQuery;
use WPML\Infrastructure\WordPress\Component\StringPackage\Application\Query\UntranslatedTypesCountQuery as PackageUntranslatedTypesCountQuery;
use WPML\Infrastructure\WordPress\Component\Taxonomy\Application\Query\UntranslatedTypesCountQuery as TaxonomyUntranslatedTypesCountQuery;
use WPML\Infrastructure\WordPress\Component\Taxonomy\Application\Query\UntranslatedTypesCountBeforeSetupQuery as TaxonomyUntranslatedTypesCountBeforeSetupQuery;
use WPML\Infrastructure\WordPress\Component\Translation\Application\Repository\StringPackageTranslatorNoteRepository;
use WPML\Infrastructure\WordPress\Component\WordsToTranslate\Domain\Post\PostBeforeSetupQuery;
use WPML\Legacy\Component\Language\Application\Query\AutomaticTranslationsSupportInfoDecoratorForLanguagesQuery;
use WPML\Legacy\Component\Language\Application\Query\LanguagesQuery;
use WPML\Legacy\Component\Translation\Sender\AteResendGuard;
use WPML\Legacy\Component\Translation\Sender\ErrorMapper\ErrorMapper;
use WPML\Legacy\Component\Translation\Sender\ErrorMapper\LegacyAteJobCreationError;
use WPML\Legacy\Component\Translation\Sender\ErrorMapper\TranslationServiceUnavailable;
use WPML\Legacy\Component\Translation\Sender\ErrorMapper\UnsupportedLanguagesInTranslationService;
use WPML\Legacy\Component\WordsToTranslate\Domain\Post\BeforeSetupJobQuery;
use WPML\Legacy\Port\Plugin;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\DashboardController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\DashboardRequirements;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\TranslateEverything\EnableController;
use WPML\UserInterface\Web\Core\Component\Notices\PromoteUsingDashboard\Application\Repository\ManualTranslationsCountRepositoryInterface;
use WPML\UserInterface\Web\Core\Component\Notices\PromoteUsingDashboard\Application\Service\ManualTranslationsCountService;
use WPML\UserInterface\Web\Core\Component\Preferences\Application\LanguagePreferencesLoader;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Endpoint\GetItemsEndpoint;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings\PostTypesTranslationController;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings\LanguagesController;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations\DashboardTabBody;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations\GlossaryTabBody;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations\ImproveTranslationsTabBody;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations\MediaTabBody;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations\MenusTabBody;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations\StoreUrlsTabBody;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations\StringsTabBody;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations\TabStripRenderer;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations\TaxonomyTabBody;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations\TranslationJobsTabBody;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations\TranslationTasksTabBody;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations\TranslationsTabStripDispatcher;
use WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Api;
use WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\ExistingPage\PostEditPage;
use WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\ExistingPage\PostListingPage;

return [
  // a blog license — their tabs are gated off there — and the Dashboard
  TranslationsTabStripDispatcher::class =>
    function ( DicInterface $dic ) {
      $tmAllowed   = $dic->make( DashboardRequirements::class );
      $isTmAllowed = $tmAllowed->requirementsMet();

      return new TranslationsTabStripDispatcher(
        $tmAllowed,
        $isTmAllowed ? $dic->make( DashboardController::class ) : null,
        $isTmAllowed ? $dic->make( DashboardTabBody::class ) : null,
        $dic->make( TabStripRenderer::class ),
        $isTmAllowed ? $dic->make( TranslationJobsTabBody::class ) : null,
        $dic->make( MenusTabBody::class ),
        $dic->make( TaxonomyTabBody::class ),
        $dic->make( StringsTabBody::class ),
        $dic->make( MediaTabBody::class ),
        $isTmAllowed ? $dic->make( TranslationTasksTabBody::class ) : null,
        $dic->make( StoreUrlsTabBody::class ),
        $isTmAllowed ? $dic->make( GlossaryTabBody::class ) : null,
        $isTmAllowed ? $dic->make( ImproveTranslationsTabBody::class ) : null
      );
    },
  \WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\ContentStats\Controller::class =>
    [ 'api' => Api::class, ],
  \WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\PostHog\Controller::class =>
    [ 'api' => Api::class, 'plugin' => Plugin::class, ],
  \WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\PostHog\DashboardSessionScriptController::class =>
    [ 'api' => Api::class, ],
  TranslationService::class                                                                    =>
    [ 'batchBuilder' => StringBatchBuilder::class ],
  TranslatorNoteService::class                                                                 =>
    [
      'stringPackageTranslatorNoteRepo' =>
        StringPackageTranslatorNoteRepository::class
    ],
  BeforeSetupJobQuery::class                                                                   =>
    [
      'jobQuery' =>
        \WPML\Legacy\Component\WordsToTranslate\Domain\Post\JobQuery::class
    ],

  PostCalculator::class                                                                        =>
    function ( DicInterface $dic, PluginInterface $plugin ) {
      $isSetupComplete = $plugin->isSetupComplete();

      return new PostCalculator(
        $dic->make( $isSetupComplete ? PostQueryInterface::class : PostBeforeSetupQuery::class ),
        $dic->make( $isSetupComplete ? PostJobQueryInterface::class : BeforeSetupJobQuery::class ),
        $dic->make( WordsToTranslate::class ),
        $dic->make( PostContentFilterInterface::class ),
        $dic->make( LanguagesQueryInterface::class )
      );
    },

  JobPriorityService::class =>
    [
      'postDataQuery'   => PostDataQueryInterface::class,
      'stringDataQuery' => StringDataQueryInterface::class,
      'termDataQuery'   => TermDataQueryInterface::class,
    ],

  ClassificationContextBuilder::class =>
    function ( DicInterface $dic ) {
      return new ClassificationContextBuilder(
        $dic->make( SiteSettingsQueryInterface::class ),
        [],
        [],
        []
      );
    },

  UntranslatedService::class =>
    function ( DicInterface $dic, PluginInterface $plugin ) {
      if ( $plugin->isSetupComplete() ) {
        $queries = [
          $dic->make( PostUntranslatedTypesCountQuery::class ),
          $dic->make( TaxonomyUntranslatedTypesCountQuery::class ),
        ];

        if ( defined( 'WPML_ST_VERSION' ) ) {
          $queries[] = $dic->make( PackageUntranslatedTypesCountQuery::class );
          $queries[] = $dic->make( StringUntranslatedTypesCountQuery::class );
        }
      } else {
        $queries = [
          $dic->make( UntranslatedTypesCountBeforeSetupQuery::class ),
          $dic->make( TaxonomyUntranslatedTypesCountBeforeSetupQuery::class ),
        ];

        if ( defined( 'WPML_ST_VERSION' ) ) {
          $queries[] = $dic->make( PackageUntranslatedTypesCountQuery::class );
          $queries[] = $dic->make( StringUntranslatedTypesCountQuery::class );
        }
      }

      return new UntranslatedService(
        $queries,
        $dic->make( TranslationEditorService::class )
      );
    },

  AutomaticTranslationsSupportInfoDecoratorForLanguagesQuery::class =>
    [ 'languagesQuery' => LanguagesQuery::class ],

  \WPML\Legacy\Component\Language\Application\Query\ProspectiveLanguagesDecoratorForLanguagesQuery::class =>
    [ 'languagesQuery' => LanguagesQuery::class ],

  EnableController::class               =>
    [
      'languagesQuery' => AutomaticTranslationsSupportInfoDecoratorForLanguagesQuery::class
    ],
    PostTypesTranslationController::class =>
    [
      'languagesQueryWithAutomaticSupport' => AutomaticTranslationsSupportInfoDecoratorForLanguagesQuery::class,
    ],
    LanguagesController::class            =>
    [
      'languagesQueryWithAutomaticSupport' => AutomaticTranslationsSupportInfoDecoratorForLanguagesQuery::class,
    ],
    LanguagePreferencesLoader::class      =>
    [
      'languagesQuery' => AutomaticTranslationsSupportInfoDecoratorForLanguagesQuery::class,
      'pluginInterface'=> Plugin::class,
    ],
    ValidatorInterface::class             =>
    function ( DicInterface $dic ) {
      return new CompositeValidator(
        [
          new ElementTargetLanguageValidator(),
          new CompletedTranslationValidator( $dic->make( CompletedTranslationDetector::class ) )
        ],
        new EmptyMethodsValidator()
      );
    },
  AteResendGuard::class                 =>
    function ( DicInterface $dic ) {
      return AteResendGuard::create(
        $dic->make( NewestJobsForPairsQueryInterface::class ),
        $dic->make( StringBatchRepositoryInterface::class )
      );
    },
  ErrorMapper::class                    =>
    function ( DicInterface $dic ) {
      return new ErrorMapper(
        [
          $dic->make( UnsupportedLanguagesInTranslationService::class ),
          $dic->make( TranslationServiceUnavailable::class ),
          $dic->make( LegacyAteJobCreationError::class )
        ]
      );
    },
  ManualTranslationsCountService::class =>
    function ( DicInterface $dic ) {
      return new ManualTranslationsCountService(
        $dic->make( ManualTranslationsCountRepositoryInterface::class ),
        $dic->make( UserQueryInterface::class ),
        [
          $dic->make( PostListingPage::class ),
          $dic->make( PostEditPage::class ),
        ]
      );
    },
  RequirementsService::class            => function (
    DicInterface $dic
  ) {
    $requirements = [
      $dic->make( MemoryLimitRequirement::class ),
      $dic->make( PHPVersionRequirement::class ),
      $dic->make( DatabaseVersionRequirement::class ),
      $dic->make( RestEnabledRequirement::class ),
      $dic->make( SimpleXMLExtensionRequirement::class ),
      $dic->make( WordPressVersionRequirement::class ),
      $dic->make( EvalFunctionRequirement::class ),
      $dic->make( LibXmlVersionRequirement::class ),
      $dic->make( MbstringExtensionRequirement::class ),
      $dic->make( StackSizeRequirement::class ),

    ];

    return new RequirementsService(
      $requirements,
      $dic->make( WordPressOptionCache::class ),
      $dic->make( RequirementsRefreshLockInterface::class )
    );
  },

  CheckRestIsEnabled::class => [ 'cache' => WordPressOptionCache::class ],

  \WPML\Core\Component\WordsToTranslate\Domain\Post\Provider::class =>
    [
      'postTermsLoader' => \WPML\Core\Component\WordsToTranslate\Domain\Post\PostTermsLoader::class,
    ],

    \WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Endpoint\GetItemsEndpoint::class =>
    function ( DicInterface $dic ) {
      return new GetItemsEndpoint(
        $dic->make( UntranslatedService::class ),
        $dic->make( \WPML\Core\Component\WordsToTranslate\Application\Service\WordsToTranslateService::class ),
        $dic->make( PluginInterface::class ),
        $dic->make( LanguagesQueryInterface::class ),
        $dic->make( \WPML\Core\SharedKernel\Component\Language\Application\ProspectiveLanguages::class )
      );
    },

    \WPML\Core\Component\WordsToTranslate\Domain\Provider::class =>
    function ( DicInterface $dic, PluginInterface $plugin ) {
      if ( $plugin->isSetupComplete() ) {
        return new \WPML\Core\Component\WordsToTranslate\Domain\Provider(
          [
           $dic->make( \WPML\Core\Component\WordsToTranslate\Domain\Post\Provider::class ),
           $dic->make( \WPML\Core\Component\WordsToTranslate\Domain\Taxonomy\Provider::class ),
           $dic->make( \WPML\Core\Component\WordsToTranslate\Domain\Strings\Provider::class ),
           $dic->make( \WPML\Core\Component\WordsToTranslate\Domain\StringBatch\Provider::class ),
           $dic->make( \WPML\Core\Component\WordsToTranslate\Domain\StringPackage\Provider::class ),
          ]
        );
      }

      return new \WPML\Core\Component\WordsToTranslate\Domain\Provider(
        [
          $dic->make(
            \WPML\Core\Component\WordsToTranslate\Domain\Post\ProviderBeforeSetup::class,
            [
              'postQuery' => PostBeforeSetupQuery::class,
              'jobQuery'  => BeforeSetupJobQuery::class,
              'translationQuery' => \WPML\Core\Component\WordsToTranslate\Domain\Post\Query\TranslationQueryInterface::class,
            ]
          ),
          $dic->make(
            \WPML\Core\Component\WordsToTranslate\Domain\Taxonomy\ProviderBeforeSetup::class,
            [
              'termQuery' => \WPML\Infrastructure\WordPress\Component\WordsToTranslate\Domain\Taxonomy\TaxonomyTermBeforeSetupQuery::class,
            ]
          ),
          $dic->make( \WPML\Core\Component\WordsToTranslate\Domain\Strings\Provider::class ),
          $dic->make( \WPML\Core\Component\WordsToTranslate\Domain\StringPackage\Provider::class ),
        ]
      );
    },

  \WPML\Core\Component\WordsToTranslate\Domain\Job\Provider::class =>
    function ( DicInterface $dic ) {
      return new \WPML\Core\Component\WordsToTranslate\Domain\Job\Provider(
        new \WPML\Legacy\Component\WordsToTranslate\Domain\Job\JobQuery(),
        new \WPML\Legacy\Component\WordsToTranslate\Domain\Job\TranslationEngineQuery(),
        [
         $dic->make( \WPML\Core\Component\WordsToTranslate\Domain\Post\Provider::class ),
         $dic->make( \WPML\Core\Component\WordsToTranslate\Domain\Strings\Provider::class ),
         $dic->make( \WPML\Core\Component\WordsToTranslate\Domain\StringBatch\Provider::class ),
         $dic->make( \WPML\Core\Component\WordsToTranslate\Domain\StringPackage\Provider::class ),
        ],
        new \WPML\Legacy\Component\WordsToTranslate\Domain\Job\PtcEngineStatusQuery(
          $dic->make( \WPML\Core\Component\ATE\Application\Service\PtcEngineStatus::class )
        ),
        $dic->make( \WPML\Core\Component\WordsToTranslate\Domain\Evidence\ManifestBuilder::class )
      );
    },
];
