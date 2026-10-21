<?php


use WPML\Core\Component\ReportContentStats\Domain\Repository\ContentSnapshotRepositoryInterface;
use WPML\Core\Component\ReportContentStats\Domain\Repository\TeaSnapshotRepositoryInterface;
use WPML\Core\Component\ReportContentStats\Domain\Repository\DailyTranslationCountRepositoryInterface;
use WPML\Core\Component\ReportContentStats\Domain\Repository\LastTranslationCompletedRepositoryInterface;
use WPML\Core\Port\PluginInterface;
use WPML\Core\SharedKernel\Component\Server\Domain\CacheInterface;
use WPML\Core\SharedKernel\Component\Server\Domain\CheckRestIsEnabledInterface;
use WPML\Core\SharedKernel\Component\Server\Domain\ServerInfoInterface;
use WPML\Infrastructure\WordPress\Component\ReportContentStats\Domain\Repository\ContentSnapshotRepository;
use WPML\Infrastructure\WordPress\Component\ReportContentStats\Domain\Repository\TeaSnapshotRepository;
use WPML\Infrastructure\WordPress\Component\ReportContentStats\Domain\Repository\DailyTranslationCountRepository;
use WPML\Infrastructure\WordPress\Component\ReportContentStats\Domain\Repository\LastTranslationCompletedRepository;
use WPML\Infrastructure\WordPress\SharedKernel\Server\Application\CheckRestIsEnabled;
use WPML\Infrastructure\WordPress\SharedKernel\Server\Application\ServerInfo;
use WPML\Infrastructure\WordPress\SharedKernel\Server\Application\WordPressTransientCache;
use WPML\Legacy\Port\Plugin;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetPopulatedItemSections\PopulatedItemSectionsFilterInterface;
use WPML\UserInterface\Web\Core\Component\Notices\PromoteUsingDashboard\Application\Repository\DashboardTranslationsRepositoryInterface;
use WPML\UserInterface\Web\Core\Component\Notices\PromoteUsingDashboard\Application\Repository\ManualTranslationsCountRepositoryInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Repository\PostTypesSinceRepositoryInterface;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\NoticeStartUsingDashboard\Application\Repository\DashboardTranslationsRepository;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\NoticeStartUsingDashboard\Application\Repository\ManualTranslationsCountRepository;

return [

  \WPML\Core\Port\Persistence\DatabaseSchemaInfoInterface::class =>
    \WPML\Infrastructure\WordPress\Port\Persistence\DatabaseSchemaInfo::class,

  \WPML\Core\Component\Translation\Domain\PreviousState\PreviousStateQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translation\Domain\PreviousState\PreviousStateQuery::class,

  \WPML\Core\Component\Translation\Domain\PreviousState\PreviousStateRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translation\Domain\PreviousState\PreviousStateRepository::class,

  \WPML\Core\SharedKernel\Component\User\Application\Query\UserQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\User\Application\Query\UserQuery::class,

  \WPML\Core\Component\Post\Application\Query\SearchQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Item\Application\Query\SearchQuery::class,

  \WPML\Core\SharedKernel\Component\Post\Domain\Repository\RepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\SharedKernel\Post\Domain\Repository\Repository::class,

  \WPML\Core\Component\Post\Application\Query\HierarchicalPostQueryInterface::class =>
    \WPML\Legacy\Component\Post\Application\Query\HierarchicalPostQuery::class,

  \WPML\Core\Component\Post\Application\Query\PermalinkQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Item\Application\Query\PermalinkQuery::class,

  \WPML\Core\Component\Post\Application\Query\PublicationStatusQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Item\Application\Query\PublicationStatusQuery::class,

  \WPML\Core\Component\Post\Application\Query\TaxonomyQueryInterface::class =>
    \WPML\Legacy\Component\Post\Application\Query\TaxonomyQuery::class,

  \WPML\Core\Component\Translation\Application\Repository\TranslatorNoteRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translation\Application\Repository\PostTranslatorNoteRepository::class,

  \WPML\Core\Component\Translation\Application\Repository\TranslationRepositoryInterface::class =>
    \WPML\Legacy\Component\Translation\Application\Repository\TranslationRepository::class,

  \WPML\Core\Component\Translation\Application\Repository\TranslateEverythingCompletedMapsInterface::class =>
    \WPML\Legacy\Component\Translation\Application\Repository\TranslateEverythingCompletedMaps::class,

  \WPML\Core\Component\Translation\Application\Authorization\TranslationGroupAuthorizationInterface::class =>
    \WPML\Legacy\Component\Translation\Application\Authorization\TranslationGroupAuthorization::class,

  \WPML\Core\Component\Translation\Application\Repository\PendingTranslationGroupRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translation\Application\Repository\PendingTranslationGroupRepository::class,

  \WPML\Core\SharedKernel\Component\Post\Application\Query\TranslatableTypesQueryInterface::class =>
    \WPML\Legacy\Component\Post\Application\Query\TranslatableTypesQuery::class,

  \WPML\Core\SharedKernel\Component\Taxonomy\Application\Query\TranslatableTaxonomiesQueryInterface::class =>
    \WPML\Legacy\Component\Taxonomy\Application\Query\TranslatableTaxonomiesQuery::class,

  \WPML\Core\SharedKernel\Component\String\Application\Query\StringLanguageQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\String\Application\Query\StringLanguageQuery::class,

  \WPML\Core\SharedKernel\Component\String\Application\Repository\StringBatchRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\String\Repository\StringBatchRepository::class,

  \WPML\Core\Component\Translation\Application\String\Query\StringsFromBatchQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translation\Application\String\Query\StringsFromBatchQuery::class,

  \WPML\Core\Component\Translation\Application\Query\TranslationBatchesQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translation\Application\Query\TranslationBatchesQuery::class,

  \WPML\Core\Component\Translation\Application\Query\NeedsUpdateCreatedInCteQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translation\Application\Query\NeedsUpdateCreatedInCteQuery::class,

  \WPML\Core\Component\Translation\Application\Query\TranslationStatusQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translation\Application\Query\TranslationStatusQuery::class,

  \WPML\UserInterface\Web\Core\Component\Notices\WarningTranslationEdit\Application\TranslationEditorInterface::class =>
    \WPML\UserInterface\Web\Legacy\Component\Translation\TranslationEditor::class,

  \WPML\UserInterface\Web\Core\Port\Asset\AssetInterface::class =>
    \WPML\UserInterface\Web\Infrastructure\WordPress\Port\Asset\Asset::class,

  \WPML\Core\Port\Remote\RemoteInterface::class =>
    \WPML\Infrastructure\WordPress\Port\Remote\Remote::class,

  \WPML\Core\Component\Translation\Application\Query\ItemLanguageQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translation\Application\Query\RegularItemsAndStringsLanguageQuery::class,

  \WPML\Core\Component\Translation\Application\Query\TranslationQueryInterface::class => \WPML\Infrastructure\WordPress\Component\Translation\Application\Query\RegularItemsAndStringsTranslationQuery::class,

  \WPML\Core\Component\Translation\Application\Query\JobQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translation\Application\Query\JobQuery::class,

  \WPML\Core\Component\ATE\Application\Query\AccountInterface::class =>
    \WPML\Legacy\Component\ATE\Application\Query\Account::class,

  \WPML\Core\Component\ATE\Application\Query\WebsiteContextQueryInterface::class =>
    \WPML\Legacy\Component\ATE\Application\Query\WebsiteContextQuery::class,

  \WPML\Core\Component\Translation\Application\Query\TranslateEverythingEnabledQueryInterface::class =>
    \WPML\Legacy\Component\Translation\Application\Query\TranslateEverythingEnabledQuery::class,

  \WPML\Core\Component\ATE\Application\Query\GlossaryInterface::class =>
    \WPML\Legacy\Component\ATE\Application\Query\Glossary::class,

  \WPML\Core\Component\ATE\Application\Query\NormalizedSuggestionsInterface::class =>
    \WPML\Legacy\Component\ATE\Application\Query\NormalizedSuggestions::class,

  \WPML\Core\Port\Persistence\OptionsInterface::class =>
    \WPML\Infrastructure\WordPress\Port\Persistence\Options::class,

  \WPML\Core\Port\Persistence\GroupedOptionsInterface::class =>
    \WPML\Infrastructure\WordPress\Port\Persistence\GroupedOptions::class,

  \WPML\Core\Port\Persistence\QueryHandlerInterface::class =>
    \WPML\Infrastructure\WordPress\Port\Persistence\QueryHandler::class,

  \WPML\Core\Port\Persistence\QueryPrepareInterface::class =>
    \WPML\Infrastructure\WordPress\Port\Persistence\QueryPrepare::class,

  \WPML\Core\Port\Persistence\DatabaseAlterInterface::class =>
    \WPML\Infrastructure\WordPress\Port\Persistence\DatabaseAlter::class,

  \WPML\Core\Port\Persistence\DatabaseWriteInterface::class =>
    \WPML\Infrastructure\WordPress\Port\Persistence\DatabaseWrite::class,

  \WPML\Core\Component\Translation\Domain\Sender\TranslationSenderInterface::class =>
    \WPML\Legacy\Component\Translation\Sender\TranslationSender::class,

  \WPML\Legacy\Component\Translation\Sender\AteResendGuardInterface::class =>
    \WPML\Legacy\Component\Translation\Sender\AteResendGuard::class,

  \WPML\Core\Component\Translation\Application\Service\UserSendFlightInterface::class =>
    \WPML\Legacy\Component\Translation\Sender\UserSendFlight::class,

  \WPML\Core\Component\Translation\Application\Query\NewestJobsForPairsQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translation\Application\Query\NewestJobsForPairsQuery::class,

  \WPML\Core\Component\Translation\Domain\Sender\DuplicationSenderInterface::class =>
    \WPML\Legacy\Component\Translation\Sender\DuplicationSender::class,

  \WPML\Core\Component\Translation\Application\Query\PostTranslationQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translation\Application\Query\PostTranslationQuery::class,

  \WPML\Core\Component\Translation\Application\Query\UnsolvableJobsQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translation\Application\Query\UnsolvableJobsQuery::class,

  \WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface::class =>
    \WPML\Legacy\Component\Language\Application\Query\ProspectiveLanguagesDecoratorForLanguagesQuery::class,

  \WPML\Core\Component\Translation\Application\Service\TranslationService\BatchBuilder\BatchBuilderInterface::class =>
    \WPML\Core\Component\Translation\Application\Service\TranslationService\BatchBuilder\BatchBuilder::class,

  \WPML\Core\Component\Translation\Application\String\Repository\StringBatchRepositoryInterface::class =>
    \WPML\Legacy\Component\Translation\Application\String\Repository\StringBatchRepository::class,

  \WPML\Core\Component\Translation\Application\String\Query\UnsentStringBatchesQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translation\Application\String\Query\UnsentStringBatchesQuery::class,

  \WPML\Core\SharedKernel\Component\Translator\Domain\Query\TranslatorsQueryInterface::class =>
    \WPML\Legacy\Component\Translator\Domain\Query\TranslatorsQuery::class,

  \WPML\Core\SharedKernel\Component\Translator\Domain\Query\TranslatorLanguagePairsQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translator\Domain\Query\TranslatorLanguagePairsQuery::class,

  \WPML\Core\SharedKernel\Component\TranslationProxy\Domain\Query\RemoteTranslationServiceQueryInterface::class =>
    \WPML\Legacy\Component\TranslationProxy\Domain\Query\RemoteTranslationServiceQuery::class,

  \WPML\Core\Component\TranslationProxy\Application\Service\TranslationProxyServiceInterface::class =>
    \WPML\Legacy\Component\TranslationProxy\Application\Service\TranslationProxyService::class,

  \WPML\Core\Port\Event\DispatcherInterface::class =>
    \WPML\Infrastructure\WordPress\Port\Event\Dispatcher::class,

  \WPML\Core\Component\Translation\Domain\Links\CollectorInterface::class =>
    \WPML\Legacy\Component\Translation\Domain\Links\Collector::class,

  \WPML\Core\Component\Translation\Domain\Links\AdjustLinksInterface::class =>
    \WPML\Legacy\Component\Translation\Domain\Links\AdjustLinks::class,

  \WPML\Core\Component\Translation\Domain\Links\ResolvedLinksCacheInterface::class =>
    \WPML\Legacy\Component\Translation\Domain\Links\ResolvedLinksCache::class,

  \WPML\Core\Component\Translation\Domain\Links\RepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translation\Domain\Links\Repository::class,

  \WPML\Core\SharedKernel\Component\Post\Domain\PublicationStatusDefinitionsInterface::class        =>
    \WPML\Infrastructure\WordPress\SharedKernel\Post\Domain\PublicationStatusDefinitions::class,
  \WPML\Core\Component\TranslationProxy\Application\Service\LastPickedUpDateServiceInterface::class =>
    \WPML\Legacy\Component\TranslationProxy\Application\Service\LastPickedUpDateService::class,

  \WPML\Core\Component\TranslationProxy\Application\Query\RemoteJobsQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\TranslationProxy\Application\Query\RemoteJobsQuery::class,

  \WPML\Core\Component\Post\Domain\WordCount\StripCodeInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Item\Domain\WordCount\StripCode::class,

  \WPML\Core\SharedKernel\Component\String\Domain\Repository\RepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\String\Domain\Repository\Repository::class,

  \WPML\Core\SharedKernel\Component\StringPackage\Domain\Repository\RepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\StringPackage\Domain\Repository\Repository::class,

  \WPML\Core\Component\StringPackage\Application\Query\PackageDefinitionQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\StringPackage\Application\Query\PackageDefinitionQuery::class,

  \WPML\Core\SharedKernel\Component\Post\Domain\Repository\MetadataRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\SharedKernel\Post\Domain\Repository\MetadataRepository::class,

  \WPML\Core\Component\Communication\Domain\DismissedNoticesStorageInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Communication\Domain\DismissedNoticesStorage::class,

  WPML\Core\Component\Post\Domain\WordCount\ItemContentCalculator\PostContentFilterInterface::class =>
    WPML\Infrastructure\WordPress\Component\Item\Domain\WordCount\ItemContentCalculator\PostContentFilter::class,

  WPML\Core\Component\Post\Domain\WordCount\Calculator\WordCountInterface::class =>
    WPML\Core\Component\Post\Domain\WordCount\Calculator\WordsToTranslateWordCount::class,

  \WPML\Core\Component\ATE\Domain\Credits\Repository\CreditsInProgressRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\ATE\Domain\Credits\Repository\CreditsInProgressRepository::class,

  \WPML\Core\Component\ATE\Application\Service\EnginesServiceInterface::class =>
    \WPML\Legacy\Component\ATE\Application\Service\EnginesService::class,

  \WPML\Core\Component\ATE\Application\Service\ActiveEngineQueryInterface::class =>
    \WPML\Legacy\Component\ATE\Application\Service\ActiveEngineQuery::class,

  \WPML\Core\Component\Translation\Application\Service\UnsolvableJobIdsSnapshotInterface::class =>
    \WPML\Legacy\Component\Translation\Application\Service\UnsolvableJobIdsSnapshot::class,

  \WPML\Core\Component\ReportContentStats\Domain\Query\OriginalContentStatsQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\ReportContentStats\Domain\Query\OriginalContentStatsQuery::class,

  \WPML\Core\Component\ReportContentStats\Domain\Query\TranslationCoverageStatsQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\ReportContentStats\Domain\Query\TranslationCoverageStatsQuery::class,

  \WPML\Core\Component\ReportContentStats\Domain\Repository\LastSentRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\ReportContentStats\Domain\Repository\LastSentRepository::class,

  \WPML\Core\Component\ReportContentStats\Domain\Repository\EventReasonRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\ReportContentStats\Domain\Repository\EventReasonRepository::class,

  \WPML\Core\Component\ReportContentStats\Domain\Repository\RetryRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\ReportContentStats\Domain\Repository\RetryRepository::class,

  \WPML\Core\Component\ReportContentStats\Domain\Repository\PostTypesStatsRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\ReportContentStats\Domain\Repository\PostTypesStatsRepository::class,

  \WPML\Core\Component\ReportContentStats\Domain\Repository\PostTypesToCalculateRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\ReportContentStats\Domain\Repository\PostTypesToCalculateRepository::class,

  \WPML\Core\Component\ReportContentStats\Domain\Repository\ProcessingLockRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\ReportContentStats\Domain\Repository\ProcessingLockRepository::class,

  LastTranslationCompletedRepositoryInterface::class =>
    LastTranslationCompletedRepository::class,

  DailyTranslationCountRepositoryInterface::class =>
    DailyTranslationCountRepository::class,

  ContentSnapshotRepositoryInterface::class =>
    ContentSnapshotRepository::class,
  TeaSnapshotRepositoryInterface::class =>
    TeaSnapshotRepository::class,

  \WPML\Core\Component\ReportContentStats\Domain\Query\PublishedPostCountQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\ReportContentStats\Domain\Query\PublishedPostCountQuery::class,

  \WPML\Core\SharedKernel\Component\ATE\Application\Query\SiteIDQueryInterface::class =>
    \WPML\Legacy\Component\ATE\Application\Query\SiteIDQuery::class,

  \WPML\Core\SharedKernel\Component\ATE\Application\Query\SiteSharedKeyQueryInterface::class =>
    \WPML\Legacy\Component\ATE\Application\Query\SiteSharedKeyQuery::class,

  \WPML\Core\SharedKernel\Component\ATE\Application\Service\AtePingerInterface::class =>
    \WPML\Legacy\Component\ATE\Application\Service\AtePinger::class,

  \WPML\Core\SharedKernel\Component\ATE\Application\Service\AteActivationInterface::class =>
    \WPML\Legacy\Component\ATE\Application\Service\AteActivation::class,

  \WPML\Core\SharedKernel\Component\ATE\Application\Service\TeaLoggerInterface::class =>
    \WPML\Legacy\Component\ATE\Application\Service\TeaLogger::class,

  \WPML\Core\SharedKernel\Component\ATE\Application\Service\TranslateEverythingStateInterface::class =>
    \WPML\Legacy\Component\ATE\Application\Service\TranslateEverythingState::class,

  \WPML\Core\SharedKernel\Component\ATE\Application\Repository\AteReachabilityRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\ATE\Application\Repository\AteReachabilityRepository::class,

  \WPML\Core\SharedKernel\Component\ATE\Application\Repository\ClientRestrictionRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\ATE\Application\Repository\ClientRestrictionRepository::class,
  \WPML\Core\SharedKernel\Component\ATE\Application\Repository\SpendCapRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\ATE\Application\Repository\SpendCapRepository::class,

  \WPML\Core\Component\ReportContentStats\Domain\ReportSenderInterface::class =>
    \WPML\Core\Component\Cdt\Application\Service\ApiWpmlOrgReportSender::class,

  \WPML\Core\Component\ReportContentStats\Application\Query\ReportCadenceQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Cdt\Query\CdtReportCadenceQuery::class,

  \WPML\Core\Component\Cdt\Application\Service\MapJsonEncoderInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Cdt\Service\WpMapJsonEncoder::class,

  \WPML\Core\Component\ReportContentStats\Application\Query\CanCollectStatsQueryInterface::class =>
    \WPML\Core\Component\ReportContentStats\Application\Query\CanCollectStatsQuery::class,

  \WPML\Core\Component\ReportContentStats\Application\Query\ContentStatsTranslatableTypesQueryInterface::class =>
    \WPML\Legacy\Component\ReportContentStats\Application\Query\ContentStatsTranslatableTypesQuery::class,

  \WPML\Core\SharedKernel\Component\Site\Application\Query\SiteUrlQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Site\Application\Query\SiteUrlQuery::class,

  \WPML\Core\SharedKernel\Component\Installer\Application\Query\WpmlSiteKeyQueryInterface::class =>
    \WPML\Legacy\SharedKernel\Installer\Application\Query\WpmlSiteKeyQuery::class,

  \WPML\Core\SharedKernel\Component\Site\Application\Query\SiteMigrationLockQueryInterface::class =>
    \WPML\Legacy\SharedKernel\Site\Application\Query\SiteMigrationLockQuery::class,

  \WPML\Core\SharedKernel\Component\Installer\Application\Query\WpmlActivePluginsQueryInterface::class =>
    \WPML\Legacy\SharedKernel\Installer\Application\Query\WpmlActivePluginsQuery::class,

  \WPML\Core\Component\WordsToTranslate\Domain\Post\Query\JobQueryInterface::class =>
    \WPML\Legacy\Component\WordsToTranslate\Domain\Post\JobQuery::class,

  \WPML\Core\Component\WordsToTranslate\Domain\Post\Query\PostQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\WordsToTranslate\Domain\Post\PostQuery::class,

  \WPML\Core\Component\WordsToTranslate\Domain\Post\Query\TranslationQueryInterface::class =>
    \WPML\Legacy\Component\WordsToTranslate\Domain\Post\TranslationQuery::class,

  \WPML\Core\Component\WordsToTranslate\Domain\Post\StoreRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\WordsToTranslate\Domain\Post\StoreRepository::class,

  \WPML\Core\Component\WordsToTranslate\Domain\Strings\Query\StringQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\WordsToTranslate\Domain\Strings\StringQuery::class,

  \WPML\Core\Component\WordsToTranslate\Domain\Strings\Query\TranslationQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\WordsToTranslate\Domain\Strings\TranslationQuery::class,

  \WPML\Core\Component\WordsToTranslate\Domain\StringBatch\Query\StringBatchQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\WordsToTranslate\Domain\StringBatch\StringBatchQuery::class,

  \WPML\Core\Component\WordsToTranslate\Domain\StringPackage\Query\JobQueryInterface::class =>
    \WPML\Legacy\Component\WordsToTranslate\Domain\StringPackage\JobQuery::class,

  \WPML\Core\Component\WordsToTranslate\Domain\StringPackage\Query\StringPackageQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\WordsToTranslate\Domain\StringPackage\StringPackageQuery::class,

  \WPML\Core\Component\WordsToTranslate\Domain\StringPackage\Query\TranslationQueryInterface::class =>
    \WPML\Legacy\Component\WordsToTranslate\Domain\StringPackage\TranslationQuery::class,

  \WPML\Core\Component\WordsToTranslate\Domain\Taxonomy\Query\TermQueryInterface::class =>
    \WPML\Legacy\Component\WordsToTranslate\Domain\Taxonomy\TermQuery::class,

  \WPML\Core\Component\WordsToTranslate\Domain\Taxonomy\Query\TranslationQueryInterface::class =>
    \WPML\Legacy\Component\WordsToTranslate\Domain\Taxonomy\TranslationQuery::class,

  \WPML\Core\Component\WordsToTranslate\Domain\Job\Query\TranslationEngineQueryInterface::class =>
    \WPML\Legacy\Component\WordsToTranslate\Domain\Job\TranslationEngineQuery::class,

  \WPML\Core\Component\WordsToTranslate\Domain\Job\Query\PtcEngineStatusQueryInterface::class =>
    \WPML\Legacy\Component\WordsToTranslate\Domain\Job\PtcEngineStatusQuery::class,

  \WPML\Core\Component\WordsToTranslate\Domain\Calculator\PrepareContent\Rules\ShortcodeInterface::class =>
    \WPML\Infrastructure\WordPress\Component\WordsToTranslate\Domain\Calculator\PrepareContent\Rules\Shortcode::class,

  \WPML\Core\Component\PostHog\Application\Repository\RetryRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\PostHog\Application\Repository\RetryRepository::class,

  \WPML\Core\Component\PostHog\Application\Repository\SetupWizardEventQueueRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\PostHog\Application\Repository\SetupWizardEventQueueRepository::class,

  \WPML\Core\Component\PostHog\Application\Repository\SetupWizardEventQueueLockInterface::class =>
    \WPML\Infrastructure\WordPress\Component\PostHog\Application\Repository\SetupWizardEventQueueLock::class,

  \WPML\Core\Component\PostHog\Domain\Repository\SetupWizardAnonymousDistinctIdRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\PostHog\Domain\Repository\SetupWizardAnonymousDistinctIdRepository::class,

  \WPML\Core\Component\PostHog\Domain\Event\SetupWizard\SetupWizardAnonymousDistinctIdInterface::class =>
    \WPML\Infrastructure\WordPress\Component\PostHog\Domain\Event\SetupWizard\SetupWizardAnonymousDistinctId::class,

  \WPML\Core\Component\PostHog\Application\Repository\PostHogStateRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\PostHog\Application\Repository\PostHogStateRepository::class,
  \WPML\Core\SharedKernel\Component\WpmlOrgClient\Domain\Api\Endpoints\PostHogRecordingInterface::class =>
    \WPML\Infrastructure\WordPress\SharedKernel\WpmlOrgClient\Domain\Api\Endpoints\PostHogRecording\PostHogRecording::class,
  \WPML\Core\SharedKernel\Component\WpmlOrgClient\Domain\Api\Endpoints\PluginReportInterface::class =>
    \WPML\Infrastructure\WordPress\SharedKernel\WpmlOrgClient\Domain\Api\Endpoints\PluginReport\PluginReport::class,
  \WPML\Core\SharedKernel\Component\Support\Application\Query\DebugInformationQueryInterface::class =>
    \WPML\Legacy\SharedKernel\Support\Application\Query\DebugInformationQuery::class,
  \WPML\Core\SharedKernel\Component\Support\Application\Repository\RepairToolsPluginInterface::class =>
    \WPML\Infrastructure\WordPress\SharedKernel\Support\RepairToolsPlugin::class,
  \WPML\Core\SharedKernel\Component\Installer\Application\Query\WpmlSubscriptionQueryInterface::class =>
    \WPML\Legacy\SharedKernel\Installer\Application\Query\WpmlSubscriptionQuery::class,
  \WPML\Core\Component\WpmlProxy\Domain\Repository\WpmlProxyRepositoryInterface::class                  =>
    \WPML\Infrastructure\WordPress\Component\WpmlProxy\Domain\Repository\WpmlProxyRepository::class,
  \WPML\Core\Component\WpmlProxy\Application\Query\ProxyRoutingRulesInterface::class                    =>
    \WPML\Legacy\Component\WpmlProxy\Application\Query\ProxyRoutingRules::class,
  PluginInterface::class                  =>
    Plugin::class,
  \WPML\Core\Component\PostHog\Application\Cookies\CookiesInterface::class =>
    \WPML\Infrastructure\WordPress\Component\PostHog\Application\Cookies\Cookies::class,

  \WPML\Core\Component\PostHog\Domain\Event\CaptureInterface::class =>
    \WPML\Infrastructure\WordPress\Component\PostHog\Domain\Event\Capture::class,

  \WPML\Core\SharedKernel\Component\PostHog\Application\Hook\FilterAllowedPagesInterface::class =>
    \WPML\UserInterface\Web\Infrastructure\WordPress\Component\PostHog\Application\Hook\FilterAllowedPages::class,

  \WPML\Core\Component\PostHog\Application\Query\PageAllowedForRecordingQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\PostHog\Application\Query\PageAllowedForRecordingQuery::class,

  \WPML\Core\Component\PostHog\Application\Repository\PostHogDefaultRequestSentRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\PostHog\Application\Repository\PostHogDefaultRequestSentRepository::class,

  \WPML\Core\Component\PostHog\Application\Repository\PostHogCacheStateRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\PostHog\Application\Repository\PostHogCacheStateRepository::class,

  \WPML\Core\Component\PostHog\Application\Repository\PostHogRefreshRateLimitRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\PostHog\Application\Repository\PostHogRefreshRateLimitRepository::class,

  \WPML\Core\Component\PostHog\Application\Repository\PostHogOptOutRecordingStateRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\PostHog\Application\Repository\PostHogOptOutRecordingStateRepository::class,

  \WPML\Core\Component\PostHog\Application\Configuration\PostHogOptOutRecordingConfigInterface::class =>
    \WPML\Infrastructure\WordPress\Component\PostHog\Application\Configuration\PostHogOptOutRecordingConfig::class,

  \WPML\Core\Component\PostHog\Application\Query\TranslationDashboardPageQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\PostHog\Application\Query\TranslationDashboardPageQuery::class,

  \WPML\Core\Component\PostHog\Domain\Event\EventInterface::class =>
    \WPML\Core\Component\PostHog\Domain\Event\Event::class,

  \WPML\Core\Component\PostHog\Domain\Repository\SetupWizardStartTimeRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\PostHog\Domain\Repository\SetupWizardStartTimeRepository::class,

  \WPML\Core\Component\PostHog\Domain\Repository\SetupWizardUUIDRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\PostHog\Domain\Repository\SetupWizardUUIDRepository::class,

  \WPML\Core\Component\PostHog\Domain\Event\SetupWizard\SetupWizardUUIDInterface::class =>
    \WPML\Infrastructure\WordPress\Component\PostHog\Domain\Event\SetupWizard\SetupWizardUUID::class,

  \WPML\Core\Component\PostHog\Domain\Repository\SetupWizardLastStepSubmissionTimeRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\PostHog\Domain\Repository\SetupWizardLastStepSubmissionTimeRepository::class,

  \WPML\UserInterface\Web\Core\Component\Dashboard\Application\Hook\DashboardPublicationStatusFilterInterface::class =>
    \WPML\UserInterface\Web\Infrastructure\WordPress\Port\Hook\DashboardPublicationStatusFilter::class,

  \WPML\UserInterface\Web\Core\Component\Dashboard\Application\Hook\DashboardItemSectionsFilterInterface::class =>
    \WPML\UserInterface\Web\Infrastructure\WordPress\Port\Hook\DashboardItemSectionsFilter::class,

  WPML\UserInterface\Web\Core\Component\Dashboard\Application\Query\DashboardTranslatableTypesQueryInterface::class =>
    WPML\UserInterface\Web\Infrastructure\WordPress\Component\Dashboard\Query\DashboardTranslatableTypesQuery::class,

  WPML\UserInterface\Web\Core\Component\Dashboard\Application\Hook\DashboardTranslatablePostTypesFilterInterface::class
  => \WPML\UserInterface\Web\Infrastructure\WordPress\Port\Hook\DashboardTranslatablePostTypesFilter::class,


  \WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetPosts\GetPostControllerInterface::class =>
    \WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetPosts\GetPostsController::class,

  WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetPosts\PostsFilterInterface::class =>
    WPML\UserInterface\Web\Infrastructure\WordPress\Port\Hook\PostsFilter::class,

  WPML\Core\Component\Post\Application\Query\SearchPopulatedTypesQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Item\Application\Query\SearchPopulatedTypesQuery::class,

  PopulatedItemSectionsFilterInterface::class =>
    \WPML\UserInterface\Web\Infrastructure\WordPress\Port\Hook\PopulatedItemSectionsFilter::class,

  DashboardTranslationsRepositoryInterface::class => DashboardTranslationsRepository::class,

  ManualTranslationsCountRepositoryInterface::class => ManualTranslationsCountRepository::class,

  \WPML\Core\Component\Translation\Domain\PreviousState\DataCompressInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translation\Domain\PreviousState\OnlyDataSerialization::class,

  CheckRestIsEnabledInterface::class => CheckRestIsEnabled::class,
  \WPML\Core\Component\MinimumRequirements\Application\Repository\RequirementsRefreshLockInterface::class =>
    \WPML\Infrastructure\WordPress\Component\MinimumRequirements\Application\Repository\RequirementsRefreshLock::class,

  ServerInfoInterface::class => ServerInfo::class,

  \WPML\Core\Component\Translation\Application\Query\HasPostsUsingNativeEditorQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translation\Application\Query\HasPostsUsingNativeEditorQuery::class,

  CacheInterface::class => WordPressTransientCache::class,

  \WPML\Core\SharedKernel\Component\Setting\Application\Query\TranslationEditorQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Setting\Application\Query\TranslationEditorQuery::class,

  \WPML\Core\SharedKernel\Component\Setting\Application\Service\TranslationEditorWriterInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Setting\Application\Service\TranslationEditorWriter::class,

  \WPML\Core\Component\Translation\Domain\Repository\JobErrorRepositoryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translation\Domain\Repository\JobErrorRepository::class,

  \WPML\UserInterface\Web\Core\Component\ATE\Application\Endpoint\EateWidget\EateWidgetInterface::class =>
    \WPML\UserInterface\Web\Legacy\Component\ATE\EateWidget::class,

  \WPML\UserInterface\Web\Core\Component\ATE\Application\Endpoint\MigrationCode\MigrationCodeProviderInterface::class =>
    \WPML\UserInterface\Web\Legacy\Component\ATE\MigrationCodeProvider::class,

  \WPML\Core\SharedKernel\Component\Item\Application\Query\ConfigExcludedPostTypesQueryInterface::class =>
    \WPML\Legacy\Component\Item\Application\Query\ConfigExcludedPostTypesQuery::class,

  PostTypesSinceRepositoryInterface::class =>
    \WPML\Legacy\Component\Post\Application\Repository\PostTypesSinceRepository::class,

  \WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Repository\PendingTranslatableOfferRepositoryInterface::class =>
    \WPML\Legacy\Component\Post\Application\Repository\PendingTranslatableOfferRepository::class,

  \WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Repository\ParkedTypesOfferRepositoryInterface::class =>
    \WPML\Legacy\Component\Post\Application\Repository\ParkedTypesOfferRepository::class,

  \WPML\Core\Component\Translation\Application\Query\Priority\PostDataQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translation\Application\Query\Priority\PostDataQuery::class,

  \WPML\Core\Component\Translation\Application\Query\Priority\SiteSettingsQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translation\Application\Query\Priority\SiteSettingsQuery::class,

  \WPML\Core\Component\Translation\Application\Query\Priority\StringDataQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translation\Application\Query\Priority\StringDataQuery::class,

  \WPML\Core\Component\Translation\Application\Query\Priority\TermDataQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Translation\Application\Query\Priority\TermDataQuery::class,


  \WPML\UserInterface\Web\Core\Component\Support\Application\SupportToolRegistryInterface::class =>
    \WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\SupportToolRegistry::class,

  \WPML\Core\Component\Cdt\Application\Repository\CdtStoreInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Cdt\Repository\CdtOptionStore::class,

  \WPML\Core\Component\Cdt\Application\Query\SiteStateQueryInterface::class =>
    \WPML\Legacy\Component\Cdt\Query\SiteStateQuery::class,

  \WPML\Core\Component\Cdt\Application\Query\LiveAteHealthQueryInterface::class =>
    \WPML\Legacy\Component\Cdt\Query\LiveAteHealthQuery::class,

  \WPML\Core\Component\Cdt\Application\Query\HealthStatementsQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Cdt\Query\HealthStatementsQuery::class,

  \WPML\Core\Component\Cdt\Application\Query\AtePollLimitQueryInterface::class =>
    \WPML\Legacy\Component\Cdt\Query\AtePollLimitQuery::class,

  \WPML\Core\Component\Cdt\Application\Query\RequestContextQueryInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Cdt\Query\WpRequestContext::class,

  \WPML\Core\Component\Cdt\Application\Query\SiteIdentityQueryInterface::class =>
    \WPML\Legacy\Component\Cdt\Query\SiteIdentityQuery::class,

  \WPML\Core\Component\Cdt\Application\Service\HttpPosterInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Cdt\Service\CdtApiPoster::class,

  \WPML\Core\Component\Cdt\Application\Service\CronSchedulerInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Cdt\Service\WpCronScheduler::class,

  \WPML\Core\Component\Cdt\Application\Service\JsonEncoderInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Cdt\Service\WpJsonEncoder::class,

  \WPML\Core\Component\Cdt\Application\Service\SubscriptionFetchInterface::class =>
    \WPML\Infrastructure\WordPress\Component\Cdt\Service\InstallerSubscriptionFetch::class,

];
