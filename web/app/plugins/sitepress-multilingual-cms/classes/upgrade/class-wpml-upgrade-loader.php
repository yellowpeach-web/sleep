<?php

use WPML\Upgrade\Commands\AddContextIndexToStrings;
use WPML\Upgrade\Commands\AddStatusIndexToStringTranslations;
use WPML\Upgrade\Commands\AddStringPackageIdIndexToStrings;
use WPML\Upgrade\Commands\AddHasTextColumnToStrings;
use WPML\Upgrade\Commands\AddContextHasTextIndexToStrings;
use WPML\Upgrade\Commands\BackfillStringHasText;
use WPML\Upgrade\Commands\RemoveTranslationPriorityTaxonomy;
use WPML\Upgrade\Command\EnableOptionsAutoloading;
use WPML\Upgrade\Commands\AddTranslationManagerCapToAdmin;
use WPML\Upgrade\Commands\RemoveRestDisabledNotice;
use WPML\Upgrade\Commands\RemoveRedundantNotices;
use WPML\Upgrade\Commands\RemoveTranslationFeedbackRemnants;
use WPML\Upgrade\Commands\RemoveLegacyWordCountState;
use WPML\Upgrade\Commands\RemoveAdminLanguageOfferNotice;
use WPML\Upgrade\Commands\DropCodeLocaleIndexFromLocaleMap;
use WPML\Upgrade\Commands\AddPrimaryKeyToLocaleMap;
use WPML\Upgrade\Commands\AddCountryColumnToLanguages;
use WPML\Upgrade\Commands\AddTypeColumnToLanguages;
use WPML\Upgrade\Commands\AddTranslationPausedColumnToLanguages;
use WPML\Upgrade\Commands\AddDisplayCodeColumnToLanguages;
use WPML\Upgrade\Commands\AddIsCustomColumnToLanguages;
use WPML\Upgrade\Commands\MigrateLanguagesToCountryModel;
use WPML\Upgrade\Commands\BackfillLanguageTypeColumn;
use WPML\Upgrade\Commands\CreateLanguagePresetsTable;
use WPML\Upgrade\Commands\CreateCountriesTable;
use WPML\Upgrade\Commands\CreateCountriesTranslationsTable;
use WPML\Upgrade\Commands\SeedCountryTranslations;
use WPML\Upgrade\Commands\SeedLanguageNameTranslations;
use WPML\Upgrade\Commands\RemoveRecordsOfDeletedTranslations;
use WPML\Upgrade\Commands\RemoveObsoleteWordCountMeta;
use WPML\Upgrade\Commands\RestoreElementorFormEmailTokens;
use WPML\Upgrade\Commands\RetireMediaTranslationStatusStore;
use WPML\Upgrade\Commands\RemoveTruncatedLanguageNameRows;
use WPML\Upgrade\Commands\CreateLanguagePresetCountriesTable;
use WPML\Upgrade\Commands\AddVisibilityTierColumnToLanguagePresets;
use WPML\Upgrade\Commands\AddRtlColumnToLanguagePresets;
use WPML\Upgrade\Commands\BackfillRtlColumns;
use WPML\Upgrade\Commands\ClearCatalogueDirectionCopies;
use WPML\Upgrade\Commands\AddLanguageColumnToLanguagePresets;
use WPML\Upgrade\Commands\AddPrecomputedColumnsToLanguagePresetCountries;
use WPML\Upgrade\Commands\AddOfferableFlagsColumnToLanguagePresetCountries;
use WPML\Upgrade\Commands\AddPickerDefaultColumnToLanguagePresetCountries;
use WPML\Upgrade\Commands\AddUnvouchedAtToLanguagePresetsTables;
use WPML\Upgrade\Commands\SeedLanguageCatalogue;
use WPML\Upgrade\Commands\RefreshLanguageCataloguePairs;
use WPML\Upgrade\Commands\RefreshLanguageCatalogueForLanguageColumn;
use WPML\Upgrade\Commands\RefreshLanguageCatalogueLocaleCoverage;
use WPML\Upgrade\Commands\RefreshLanguageCatalogueBareCodes;
use WPML\Upgrade\Commands\RefreshLanguageCataloguePairIdentity;
use WPML\Upgrade\Commands\RemoveNationalAnchorPairRows;
use WPML\Upgrade\Commands\ClearLanguagesCatalogueSyncVersion;
use WPML\Upgrade\Commands\ClearLanguagesCatalogueSyncVersionForPickerDefault;
use WPML\Upgrade\Commands\AddAutomaticColumnToIclTranslateJob;
use WPML\Upgrade\Commands\AddSentFromColumnToIclTranslateJob;
use WPML\Upgrade\Commands\AddCreatedByTranslateEverythingColumnToIclTranslateJob;
use WPML\Upgrade\Commands\AddReviewModeColumnToIclTranslateJob;
use WPML\Upgrade\Commands\AddWordsToTranslateCountLifetimeMaxColumnToIclTranslateJob;
use WPML\Upgrade\Commands\RemoveEndpointsOption;
use WPML\TM\Upgrade\Commands\AddReviewStatusColumnToTranslationStatus;
use WPML\TM\Upgrade\Commands\AddAteCommunicationRetryColumnToTranslationStatus;
use WPML\TM\Upgrade\Commands\AddAteSyncCountToTranslationJob;
use WPML\TM\Upgrade\Commands\ResetTranslatorOfAutomaticJobs;
use WPML\Upgrade\Commands\CreateBackgroundTaskTable;
use WPML\Upgrade\Commands\WidenBackgroundTaskColumns;
use WPML\Upgrade\Commands\WidenTranslateFieldTypeColumn;
use WPML\Upgrade\Commands\WidenFlagsFlagColumn;
use WPML\Upgrade\Commands\WidenStringsTitleColumn;
use WPML\Upgrade\Commands\WidenTranslateJobTitleColumn;
use WPML\Upgrade\Commands\WidenTranslateJobTranslatorIdColumn;
use WPML\Upgrade\Commands\WidenTranslateJobManagerIdColumn;
use WPML\Upgrade\Commands\CreateUrlResolutionCacheTable;
use WPML\Upgrade\Commands\CreateElementKnowledgeTable;
use WPML\TM\Upgrade\Commands\SetCorrectTranslateEverythingState;
use WPML\TM\Upgrade\Commands\MigrateTranslateEverythingCompletedOption;
use WPML\TM\Upgrade\Commands\EnableHandleMediaAutoOptionForNewInstalls;
use WPML\TM\Upgrade\Commands\CreateUnsolvableJobsTable;
use WPML\TM\Upgrade\Commands\ValidateAliasDomain;
use WPML\TM\Upgrade\Commands\BackfillCdtOptions;
use WPML\Upgrade\Commands\BackfillDefaultLanguageDefaultCategory;
use WPML\Upgrade\Commands\BackfillTranslateEverythingSinceDates;
use WPML\Upgrade\Commands\DeleteTranslationJobsBasketOption;
use WPML\Upgrade\Commands\DeleteIclAdminMessagesOption;
use WPML\Upgrade\Commands\RemoveRetiredLanguageNotices;
use WPML\Upgrade\Commands\RefreshMissingLanguagePacksNotice;
use WPML\Upgrade\Commands\DisableAutoloadForUnusedOptions;
use WPML\Upgrade\Commands\MaterializePerRequestOptionFlags;
use WPML\Upgrade\Commands\AddBcp47ColumnToLanguages;
use WPML\Upgrade\Commands\AddIsRtlColumnToLanguages;
use WPML\Upgrade\Commands\BackfillBcp47Column;
use WPML\Upgrade\Commands\BackfillDisplayCodeColumn;
use WPML\Upgrade\Commands\RepairZeroDisplayCode;
use WPML\Upgrade\Commands\AddNameHashToMetaSettings;
use WPML\Upgrade\Commands\OffloadMetaSettings;
use WPML\Upgrade\Commands\ResetLanguageNameCache;
use WPML\Upgrade\Commands\RepairExistingContentLanguageVerified;
use WPML\Upgrade\Commands\RepairSuffixedLanguageNames;
use WPML\Upgrade\Commands\AddLatestJobPerRidIndex;
use WPML\Upgrade\Commands\BackfillCapabilityIndex;
use WPML\Upgrade\Commands\RestoreTermTranslationStatusRows;
use WPML\Upgrade\Commands\ReleaseAdoptedAteJobBindings;
use WPML\Upgrade\Commands\AdoptCatalogueCodedCustomLanguages;
use WPML\Upgrade\Commands\AdoptCustomLanguagesOnCatalogueCodes;
use WPML\Upgrade\Commands\ReconcileCustomLanguageHeadMappings;
use WPML\Upgrade\Commands\NormalizeNullDefaultLocale;
use WPML\Upgrade\Commands\RemoveTroubleshootingCapabilityFromRoles;
use WPML\Upgrade\Commands\ForgetSiteWideSiteKeyNoticeDismissal;
use WPML\Upgrade\Commands\ResetStringWordCounts;

class WPML_Upgrade_Loader implements IWPML_Action {

	const TRANSIENT_UPGRADE_IN_PROGRESS = 'wpml_core_update_in_progress';

	const FRONT_END_SCOPE_ID = 'core';

	private $sitepress;

	private $upgrade_schema;

	private $settings;

	private $factory;

	private $notices;

	public function __construct(
		SitePress $sitepress,
		WPML_Upgrade_Schema $upgrade_schema,
		WPML_Settings_Helper $settings,
		WPML_Notices $wpml_notices,
		WPML_Upgrade_Command_Factory $factory
	) {
		$this->sitepress      = $sitepress;
		$this->upgrade_schema = $upgrade_schema;
		$this->settings       = $settings;
		$this->notices        = $wpml_notices;
		$this->factory        = $factory;
	}

	public function add_hooks() {
		add_action( 'wpml_loaded', array( $this, 'wpml_upgrade' ) );
		register_activation_hook( WPML_PLUGIN_PATH . '/' . WPML_PLUGIN_FILE, array( $this, 'wpml_upgrade' ) );

		add_action( 'admin_init', array( MigrateLanguagesToCountryModel::class, 'reapplyAteCountryMappingIfDeferredThrottled' ) );
	}

	public function wpml_upgrade() {
		if ( get_transient( self::TRANSIENT_UPGRADE_IN_PROGRESS ) ) {
			return;
		}

		$upgrade = new WPML_Upgrade( $this->get_command_definitions(), $this->sitepress, $this->factory, null, self::FRONT_END_SCOPE_ID );
		$upgrade->run();
	}

	public function get_command_definitions() {
		return [
			$this->factory->create_command_definition( 'WPML_Upgrade_Fix_Non_Admin_With_Admin_Cap', [], [ 'admin' ] ),
			$this->factory->create_command_definition( 'WPML_Upgrade_Table_Translate_Job_For_3_9_0', [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end' ] ),
			$this->factory->create_command_definition( 'WPML_Upgrade_Remove_Translation_Services_Transient', [], [ 'admin' ] ),
			$this->factory->create_command_definition( 'WPML_Add_UUID_Column_To_Translation_Status', [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( 'WPML_Upgrade_Element_Type_Length_And_Collation', [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end' ] ),
			$this->factory->create_command_definition( 'WPML_Upgrade_Add_Word_Count_Column_To_Strings', [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( 'WPML_Upgrade_Media_Without_Language', [ $this->upgrade_schema->get_wpdb(), $this->sitepress->get_default_language() ], [ 'admin', 'ajax' ] ),
			$this->factory->create_command_definition( \WPML\Upgrade\Commands\InitializeTranslatablePostTypes::class, [ $this->upgrade_schema->get_wpdb(), $this->sitepress ], [ 'admin', 'ajax', 'cli' ] ),
			$this->factory->create_command_definition( 'WPML_Upgrade_Chinese_Flags', [ 'wpdb' => $this->sitepress->wpdb() ], [ 'admin' ] ),
			$this->factory->create_command_definition( 'WPML_Upgrade_Add_Editor_Column_To_Icl_Translate_Job', [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( 'WPML_Upgrade_WPML_Site_ID', [], [ 'admin' ] ),
			$this->factory->create_command_definition( 'WPML_Upgrade_WPML_Site_ID_Remaining', [], [ 'admin' ] ),
			$this->factory->create_command_definition( 'WPML_Upgrade_Add_Location_Column_To_Strings', [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( 'WPML_Upgrade_Add_Wrap_Column_To_Translate', [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( 'WPML_Upgrade_Add_Wrap_Column_To_Strings', [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( AddContextIndexToStrings::class, array( $this->upgrade_schema ), array( 'admin', 'ajax', 'front-end' ) ),
			$this->factory->create_command_definition( AddStatusIndexToStringTranslations::class, array( $this->upgrade_schema ), array( 'admin', 'ajax', 'front-end' ) ),
			$this->factory->create_command_definition( AddStringPackageIdIndexToStrings::class, array( $this->upgrade_schema ), array( 'admin', 'ajax', 'front-end', 'cli' ) ),
			$this->factory->create_command_definition( AddHasTextColumnToStrings::class, array( $this->upgrade_schema ), array( 'admin', 'ajax', 'front-end', 'cli' ) ),
			$this->factory->create_command_definition( AddContextHasTextIndexToStrings::class, array( $this->upgrade_schema ), array( 'admin', 'ajax', 'front-end', 'cli' ) ),
			$this->factory->create_command_definition( BackfillStringHasText::class, array( $this->upgrade_schema ), array( 'admin', 'cli' ) ),
			$this->factory->create_command_definition( RemoveTranslationPriorityTaxonomy::class, array( $this->upgrade_schema, $this->sitepress ), array( 'admin', 'ajax', 'front-end', 'cli' ) ),
			$this->factory->create_command_definition( CreateBackgroundTaskTable::class, array( $this->upgrade_schema ), array( 'admin' ) ),
			$this->factory->create_command_definition( \WPML\Upgrade\Commands\CreateMediaUrlLookupTable::class, array( $this->upgrade_schema ), array( 'admin', 'ajax', 'front-end' ) ),
			$this->factory->create_command_definition( WidenBackgroundTaskColumns::class, array( $this->upgrade_schema ), array( 'admin' ) ),
			$this->factory->create_command_definition( CreateUrlResolutionCacheTable::class, array( $this->upgrade_schema ), array( 'admin', 'ajax', 'front-end' ) ),
			$this->factory->create_command_definition( CreateElementKnowledgeTable::class, array( $this->upgrade_schema ), array( 'admin', 'ajax', 'front-end', 'cli' ) ),
			$this->factory->create_command_definition( WidenTranslateFieldTypeColumn::class, array( $this->upgrade_schema ), array( 'admin', 'ajax', 'front-end' ) ),
			$this->factory->create_command_definition( WidenFlagsFlagColumn::class, array( $this->upgrade_schema ), array( 'admin', 'ajax', 'front-end' ) ),
			$this->factory->create_command_definition( WidenTranslateJobTitleColumn::class, array( $this->upgrade_schema ), array( 'admin', 'ajax', 'front-end' ) ),
			$this->factory->create_command_definition( WidenStringsTitleColumn::class, array( $this->upgrade_schema ), array( 'admin', 'ajax', 'front-end' ) ),
			$this->factory->create_command_definition( WidenTranslateJobTranslatorIdColumn::class, array( $this->upgrade_schema ), array( 'admin', 'ajax', 'front-end' ) ),
			$this->factory->create_command_definition( WidenTranslateJobManagerIdColumn::class, array( $this->upgrade_schema ), array( 'admin', 'ajax', 'front-end' ) ),
			$this->factory->create_command_definition( EnableOptionsAutoloading::class, [], [ 'admin' ] ),
			$this->factory->create_command_definition( RemoveRestDisabledNotice::class, [], [ 'admin' ] ),
			$this->factory->create_command_definition( RemoveRedundantNotices::class, [], [ 'admin' ] ),
			$this->factory->create_command_definition( RemoveAdminLanguageOfferNotice::class, [], [ 'admin' ] ),
			$this->factory->create_command_definition( RemoveTranslationFeedbackRemnants::class, [ $this->upgrade_schema ], [ 'admin' ] ),
			$this->factory->create_command_definition( RemoveLegacyWordCountState::class, [ $this->upgrade_schema->get_wpdb() ], [ 'admin' ] ),
			$this->factory->create_command_definition( ResetTranslatorOfAutomaticJobs::class, [], [ 'admin' ] ),
			$this->factory->create_command_definition( DropCodeLocaleIndexFromLocaleMap::class, array( $this->upgrade_schema ), array( 'admin', 'ajax', 'front-end' ) ),
			$this->factory->create_command_definition( AddPrimaryKeyToLocaleMap::class, array( $this->upgrade_schema ), array( 'admin', 'ajax', 'front-end' ) ),
			$this->factory->create_command_definition( AddCountryColumnToLanguages::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( AddTypeColumnToLanguages::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( AddTranslationPausedColumnToLanguages::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( CreateLanguagePresetsTable::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( CreateCountriesTable::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( CreateCountriesTranslationsTable::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end' ] ),
			$this->factory->create_command_definition( CreateLanguagePresetCountriesTable::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( AddVisibilityTierColumnToLanguagePresets::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( AddRtlColumnToLanguagePresets::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( AddLanguageColumnToLanguagePresets::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( AddPrecomputedColumnsToLanguagePresetCountries::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end' ] ),
			$this->factory->create_command_definition( AddOfferableFlagsColumnToLanguagePresetCountries::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end' ] ),
			$this->factory->create_command_definition( AddPickerDefaultColumnToLanguagePresetCountries::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( ClearLanguagesCatalogueSyncVersionForPickerDefault::class, [], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( AddUnvouchedAtToLanguagePresetsTables::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( SeedLanguageCatalogue::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( RefreshLanguageCataloguePairs::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end' ] ),
			$this->factory->create_command_definition( RefreshLanguageCatalogueForLanguageColumn::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end' ] ),
			$this->factory->create_command_definition( RefreshLanguageCatalogueLocaleCoverage::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end' ] ),
			$this->factory->create_command_definition( RefreshLanguageCataloguePairIdentity::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end' ] ),
			$this->factory->create_command_definition( RefreshLanguageCatalogueBareCodes::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end' ] ),
			$this->factory->create_command_definition( RemoveNationalAnchorPairRows::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end' ] ),
			$this->factory->create_command_definition( ClearLanguagesCatalogueSyncVersion::class, [], [ 'admin', 'ajax', 'front-end' ] ),
			$this->factory->create_command_definition( SeedCountryTranslations::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end' ] ),
			$this->factory->create_command_definition( SeedLanguageNameTranslations::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end' ] ),
			$this->factory->create_command_definition( RemoveTruncatedLanguageNameRows::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end' ] ),
			$this->factory->create_command_definition( AddDisplayCodeColumnToLanguages::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( AddIsCustomColumnToLanguages::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( MigrateLanguagesToCountryModel::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( BackfillLanguageTypeColumn::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end' ] ),
			$this->factory->create_command_definition( AddAutomaticColumnToIclTranslateJob::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( AddSentFromColumnToIclTranslateJob::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( AddCreatedByTranslateEverythingColumnToIclTranslateJob::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( AddReviewModeColumnToIclTranslateJob::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( AddWordsToTranslateCountLifetimeMaxColumnToIclTranslateJob::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( AddTMAllowedOption::class, [], [ 'admin', 'ajax', 'front-end' ] ),
			$this->factory->create_command_definition( AddTranslationManagerCapToAdmin::class, [], [ 'admin', 'ajax', 'front-end' ] ),
			$this->factory->create_command_definition( AddReviewStatusColumnToTranslationStatus::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( AddAteCommunicationRetryColumnToTranslationStatus::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'cli' ] ),
			$this->factory->create_command_definition( AddAteSyncCountToTranslationJob::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'cli' ] ),
			$this->factory->create_command_definition( 'WPML_TM_Add_TP_ID_Column_To_Translation_Status', [ $this->upgrade_schema ], array( 'admin', 'ajax', 'front-end' ) ),
			$this->factory->create_command_definition( 'WPML_TM_Add_TP_Revision_And_TS_Status_Columns_To_Translation_Status', [ $this->upgrade_schema ], array( 'admin', 'ajax', 'front-end' ) ),
			$this->factory->create_command_definition( RemoveEndpointsOption::class, [], [ 'admin', 'ajax', 'front-end' ] ),
			$this->factory->create_command_definition( SetCorrectTranslateEverythingState::class, [], [ 'admin' ] ),
			$this->factory->create_command_definition( MigrateTranslateEverythingCompletedOption::class, [], [ 'admin' ] ),
			$this->factory->create_command_definition( EnableHandleMediaAutoOptionForNewInstalls::class, [], [ 'admin' ] ),
			$this->factory->create_command_definition( CreateUnsolvableJobsTable::class, [ $this->upgrade_schema ], [ 'admin' ] ),
			$this->factory->create_command_definition( ValidateAliasDomain::class, [], [ 'admin' ] ),
			$this->factory->create_command_definition( BackfillCdtOptions::class, [], [ 'admin' ] ),
			$this->factory->create_command_definition( DisableAutoloadForUnusedOptions::class, [], [ 'admin' ] ),
			$this->factory->create_command_definition( MaterializePerRequestOptionFlags::class, [], [ 'admin' ] ),
			$this->factory->create_command_definition( AddBcp47ColumnToLanguages::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( BackfillBcp47Column::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( BackfillDisplayCodeColumn::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( RepairZeroDisplayCode::class, [ $this->upgrade_schema, $this->sitepress ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( AddIsRtlColumnToLanguages::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( BackfillRtlColumns::class, [ $this->upgrade_schema ], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( ClearCatalogueDirectionCopies::class, [], [ 'admin' ] ),
			$this->factory->create_command_definition( BackfillDefaultLanguageDefaultCategory::class, [ $this->sitepress ], [ 'admin', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( ResetLanguageNameCache::class, [ $this->sitepress ], [ 'admin' ] ),
			$this->factory->create_command_definition( RepairSuffixedLanguageNames::class, [], [ 'admin' ] ),
			$this->factory->create_command_definition( BackfillTranslateEverythingSinceDates::class, [], [ 'admin' ] ),
			$this->factory->create_command_definition( OffloadMetaSettings::class, array( $this->upgrade_schema ), array( 'admin' ) ),
			$this->factory->create_command_definition( AddNameHashToMetaSettings::class, array( $this->upgrade_schema ), array( 'admin' ) ),
			$this->factory->create_command_definition( DeleteTranslationJobsBasketOption::class, [], [ 'admin' ] ),
			$this->factory->create_command_definition( DeleteIclAdminMessagesOption::class, [], [ 'admin' ] ),
			$this->factory->create_command_definition( RemoveRetiredLanguageNotices::class, [ $this->upgrade_schema ], [ 'admin' ] ),
			$this->factory->create_command_definition( RefreshMissingLanguagePacksNotice::class, [ $this->upgrade_schema ], [ 'admin' ] ),
			$this->factory->create_command_definition( RemoveRecordsOfDeletedTranslations::class, [ $this->upgrade_schema ], [ 'admin' ] ),
			$this->factory->create_command_definition( BackfillCapabilityIndex::class, [ $this->upgrade_schema ], [ 'admin', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( RestoreTermTranslationStatusRows::class, [ $this->upgrade_schema ], [ 'admin', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( RetireMediaTranslationStatusStore::class, [ $this->upgrade_schema ], [ 'admin' ] ),
			$this->factory->create_command_definition( AddLatestJobPerRidIndex::class, [ $this->upgrade_schema ], [ 'admin', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( ReleaseAdoptedAteJobBindings::class, [ $this->upgrade_schema ], [ 'admin', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( NormalizeNullDefaultLocale::class, [], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( AdoptCatalogueCodedCustomLanguages::class, [], [ 'admin', 'ajax', 'front-end', 'cli' ] ),
			$this->factory->create_command_definition( AdoptCustomLanguagesOnCatalogueCodes::class, [], [ 'admin', 'ajax', 'cli' ] ),
			$this->factory->create_command_definition( ReconcileCustomLanguageHeadMappings::class, [], [ 'admin', 'ajax', 'cli' ] ),
			$this->factory->create_command_definition( RemoveTroubleshootingCapabilityFromRoles::class, [], [ 'admin' ] ),
			$this->factory->create_command_definition( RemoveObsoleteWordCountMeta::class, [ $this->upgrade_schema ], [ 'admin' ] ),
			$this->factory->create_command_definition( ForgetSiteWideSiteKeyNoticeDismissal::class, [], [ 'admin' ] ),
			$this->factory->create_command_definition( ResetStringWordCounts::class, [ $this->upgrade_schema ], [ 'admin' ] ),
			$this->factory->create_command_definition( RepairExistingContentLanguageVerified::class, [ $this->sitepress, $this->upgrade_schema->get_wpdb() ], [ 'admin', 'cli' ] ),
			$this->factory->create_command_definition( RestoreElementorFormEmailTokens::class, [ $this->upgrade_schema ], [ 'admin' ] ),
		];
	}
}
