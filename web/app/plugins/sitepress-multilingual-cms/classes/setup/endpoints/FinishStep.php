<?php

namespace WPML\Setup\Endpoint;

use WPML\AdminLanguageSwitcher\AdminLanguageSwitcher;
use WPML\Ajax\IHandler;
use WPML\API\Settings;
use WPML\Collect\Support\Collection;
use WPML\Core\Component\PostHog\Application\Service\Event\EventInstanceService;
use WPML\Core\LanguageNegotiation;
use WPML\Core\SharedKernel\Component\Item\Application\Query\ConfigExcludedPostTypesQueryInterface;
use WPML\FP\Either;
use WPML\LanguageEditor\SetupWizardAnalytics;
use WPML\Legacy\SharedKernel\Installer\Application\Query\WpmlActivePluginsQuery;
use WPML\LIB\WP\User;
use WPML\PostHog\Event\CaptureSetupWizardCompletedEvent;
use WPML\PostHog\FlushSetupWizardQueue;
use WPML\PostHog\RefreshRecording;
use WPML\PostHog\SetupRecordingCleanup;
use WPML\TM\ATE\AutoTranslate\Endpoint\EnableATE;
use WPML\TM\ATE\Loader\MarkPreviouslyUnsupportedContentAsCompletedInTEA\ExecutionStatus;
use WPML\TranslationRoles\Service\AdministratorRoleManager;
use WPML\UrlHandling\WPLoginUrlConverter;
use function WPML\Container\make;
use WPML\FP\Lst;
use WPML\FP\Right;
use WPML\Setup\DeferredUpgradePass;
use WPML\Setup\FurthestStep;
use WPML\Setup\MediaSetupPass;
use WPML\Setup\Option;
use WPML\TM\Menu\TranslationServices\Endpoints\Deactivate;

class FinishStep implements IHandler {

	const TEA_EXCLUDED_SINCE_DATE = '9999-12-31';

	private $administratorRoleManager;

	public function __construct(
		AdministratorRoleManager $administratorRoleManager
	) {
		$this->administratorRoleManager = $administratorRoleManager;
	}

	public function run( Collection $data ) {
		\WPML\Media\Option::prepareSetup();

		$wpmlInstallation = wpml_get_setup_instance();
		list( $originalLanguage, $translationLangs ) = self::resolveWizardLanguages();

		$hasChosenTranslateEverything = (bool) ( new \WPML\WP\OptionManager() )->get(
			Option::OPTION_GROUP,
			\WPML\TranslationMode\Endpoint\SetTranslateEverything::KEY_TRANSLATE_EVERYTHING_CHOSEN,
			false
		);
		if (
			\TranslationProxy::has_preferred_translation_service()
			&& ! $hasChosenTranslateEverything
		) {
			Option::setTranslateEverything( false );
		}

		$translateEverything = Option::getTranslateEverything();
		$wpmlInstallation->finish_step1( $originalLanguage );
		$wpmlInstallation->finish_step2( Lst::append( $originalLanguage, $translationLangs ) );
		$wpmlInstallation->finish_installation();

		self::enableFooterLanguageSwitcher();

		if ( $translateEverything ) {
			Option::setReviewMode( Option::NO_REVIEW );
		}

		$translationMode = Option::getTranslationMode();
		if ( ! Lst::includes( 'users', $translationMode ) ) {
			make( \WPML_Translator_Records::class )->delete_all();
		}

		if ( ! Lst::includes( 'manager', $translationMode ) ) {
			make( \WPML_Translation_Manager_Records::class )->delete_all();
		}


		$this->administratorRoleManager->initializeAllAdministrators();

		if ( Option::isTMAllowed( ) ) {
			if ( ! Lst::includes( 'service', $translationMode ) ) {
				make( Deactivate::class )->run( wpml_collect( [] ) );
			}

			Settings::assoc( 'translation-management', \WPML_TM_Post_Edit_TM_Editor_Mode::TM_KEY_GLOBAL_EDITOR, 'dashboard' );
		} else {
			Option::setTranslateEverything( false );
		}

		if ( Option::getTranslateEverything() ) {
			$this->makeExistingCustomPostTypesTranslatable();
		}

		$this->skipPreviouslyUnsupportedPackagesMigration();

		WPLoginUrlConverter::enable( true );
		AdminLanguageSwitcher::enable();

		$aiTranslationData = $data->get( 'ai_translation_data', null );

		$this->captureWizardFinishedEvent([
			'original_language' => $originalLanguage,
			'translation_languages' => $translationLangs,
			'ai_translation_data' => $aiTranslationData,
		]);

		try {
			SetupWizardAnalytics::clear();
		} catch ( \Throwable $e ) {
		}

		// decision reverts. wizard_completed is captured under the license step's
		FlushSetupWizardQueue::flushIfGranted();

		if ( RefreshRecording::forceRefresh( [ 'during_setup' => false ] ) ) {
			SetupRecordingCleanup::clearStarted();
		}

		try {
			MediaSetupPass::run();
		} catch ( \Throwable $e ) {
		}

		try {
			DeferredUpgradePass::run();
		} catch ( \Throwable $e ) {
		}

		return Right::of( true );
	}


	private static function resolveWizardLanguages() {
		global $wpdb;

		$optionOriginal = (string) Option::getOriginalLang();
		$optionLangs    = (array) Option::getTranslationLangs();

		$dbActive  = $wpdb && method_exists( $wpdb, 'get_col' )
			? $wpdb->get_col( "SELECT code FROM {$wpdb->prefix}icl_languages WHERE active = 1" )
			: array();
		$dbActive  = is_array( $dbActive ) ? $dbActive : array();
		$dbDefault = function_exists( 'icl_get_setting' )
			? (string) icl_get_setting( 'default_language', '' )
			: '';

		$original = '' !== $dbDefault ? $dbDefault : $optionOriginal;
		$langs    = array_values( array_unique( array_filter( array_merge(
			$optionLangs,
			array_diff( (array) $dbActive, array( $original ) )
		) ) ) );

		return array( $original, $langs );
	}

	private function skipPreviouslyUnsupportedPackagesMigration() {
		make( ExecutionStatus::class )->markPackagesAsExecuted();
	}


	private function makeExistingCustomPostTypesTranslatable() {
		$settingsHelper = make( \WPML_Settings_Helper::class );
		$sinceDates     = Option::getTranslateEverythingPostsSinceDates();
		$configExcluded = $this->postTypesExcludedByWpmlConfig();

		$customPostTypes = get_post_types( array( 'public' => true, '_builtin' => false ), 'names' );
		foreach ( $customPostTypes as $postType ) {
			$isExcluded = ! isset( $sinceDates[ $postType ] )
				|| $sinceDates[ $postType ] === self::TEA_EXCLUDED_SINCE_DATE
				|| in_array( $postType, $configExcluded, true );

			if ( $isExcluded ) {
				continue;
			}

			if ( ! apply_filters( 'wpml_is_translated_post_type', false, $postType ) ) {
				$settingsHelper->set_post_type_translatable( $postType );
			}
		}
	}


	private function postTypesExcludedByWpmlConfig() {
		global $wpml_dic;

		if ( ! $wpml_dic ) {
			return array();
		}

		try {
			return $wpml_dic->make( ConfigExcludedPostTypesQueryInterface::class )->get();
		} catch ( \Exception $e ) {
			return array();
		}
	}


	private function captureWizardFinishedEvent( $eventData ) {

		$eventData['translation_mode']            = Option::getTranslationMode();
		$eventData['language_negotiation_mode']   = LanguageNegotiation::getModeAsString();
		$eventData['domains']                     = LanguageNegotiation::getDomains() ?: [];
		$eventData['site_key']                    = function_exists( 'OTGS_Installer' ) && OTGS_Installer() ? (string) OTGS_Installer()->get_site_key( 'wpml' ) : null;
		$eventData['is_predefined_sitekey_saved'] = function_exists( 'OTGS_Installer' )
		                                            && OTGS_Installer()
		                                            && defined( 'OTGS_INSTALLER_SITE_KEY_WPML' )
		                                            && OTGS_INSTALLER_SITE_KEY_WPML
		                                            && OTGS_Installer()->get_site_key( 'wpml' ) === preg_replace( '/[^A-Za-z0-9]/', '', (string) OTGS_INSTALLER_SITE_KEY_WPML );
		$eventData['is_tm_allowed']               = Option::isTMAllowed();
		$eventData['support_step_value']          = class_exists( 'OTGS_Installer_WP_Share_Local_Components_Setting' ) && \OTGS_Installer_WP_Share_Local_Components_Setting::get_setting( 'wpml' );
		$eventData['wpml_active_plugins']         = ( new WpmlActivePluginsQuery() )->getActivePlugins();

		if ( ! empty( $eventData['ai_translation_data'] ) && is_array( $eventData['ai_translation_data'] ) ) {
			$eventData['ai_translation_step_values'] = [
				'product_or_service'  => isset( $eventData['ai_translation_data']['product_or_service'] ) ?
					sanitize_text_field( $eventData['ai_translation_data']['product_or_service'] ) :
					'',
				'website_description' => isset( $eventData['ai_translation_data']['website_description'] ) ?
					sanitize_text_field( $eventData['ai_translation_data']['website_description'] ) :
					'',
				'target_audience'     => isset( $eventData['ai_translation_data']['target_audience'] ) ?
					sanitize_text_field( $eventData['ai_translation_data']['target_audience'] ) :
					'',
			];
		} else {
			$eventData['ai_translation_step_values'] = null;
		}

		unset( $eventData['ai_translation_data'] );

		try {
			$eventData = array_merge( $eventData, SetupWizardAnalytics::summary() );
			$eventData['language_locales'] = self::configuredLocales();
		} catch ( \Throwable $e ) {
		}

		$eventData = array_merge( $eventData, FurthestStep::advance( 'finished' ) );

		$event = ( new EventInstanceService() )->getWizardCompletedEvent( $eventData );
		CaptureSetupWizardCompletedEvent::capture( $event );
	}

	private static function configuredLocales() {
		global $wpdb;

		if ( ! is_object( $wpdb ) ) {
			return array();
		}

		$locales = $wpdb->get_col( "SELECT default_locale FROM {$wpdb->prefix}icl_languages WHERE active = 1" );
		if ( ! is_array( $locales ) ) {
			return array();
		}
		$locales = array_values( array_unique( array_filter( array_map( 'strval', $locales ) ) ) );
		sort( $locales );

		return $locales;
	}

	private static function enableFooterLanguageSwitcher() {
		\WPML_Config::load_config_run();

		$lsSettings = make( \WPML_LS_Dependencies_Factory::class )->settings();

		$settings = $lsSettings->get_settings();
		$settings['statics']['footer']->set( 'show', true );

		$lsSettings->save_settings( $settings );
	}

}
