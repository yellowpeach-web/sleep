<?php

namespace WPML\Setup;

use OTGS_Installer_Subscription;
use OTGS_Installer_WP_Share_Local_Components_Setting;
use WPML\Ajax\Endpoint\Upload;
use WPML\Collect\Support\Collection;
use WPML\Core\Component\MinimumRequirements\Application\Service\RequirementsService;
use WPML\Core\Component\PostHog\Application\Service\Event\EventInstanceService;
use WPML\Core\LanguageNegotiation;
use WPML\Core\WP\App\Resources;
use WPML\Element\API\Languages;
use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Maybe;
use WPML\FP\Obj;
use WPML\Infrastructure\Dic;
use WPML\LanguageEditor\ActiveLanguages;
use WPML\LIB\WP\Option as WPOption;
use WPML\LIB\WP\User;
use WPML\Plugins;
use WPML\PostHog\Event\CaptureWizardStartedEvent;
use WPML\Setup\Endpoint\CheckTMAllowed;
use WPML\Setup\Endpoint\CurrentStep;
use WPML\Setup\Endpoint\ShouldShowWCMLMessages;
use WPML\ATE\Proxies\ProxyInterceptorLoader;
use WPML\TM\ATE\Dashboard\ATEDashboardLoader;
use WPML\TM\ATE\AutoTranslate\Endpoint\EnableATE;
use WPML\TM\Menu\TranslationMethod\TranslationMethodSettings;
use WPML\TM\ATE\ClonedSites\SetupMigration\Service as SetupMigrationService;
use WPML\TranslationMode\Endpoint\SetReviewMode;
use WPML\TranslationMode\Endpoint\SetTranslateEverything;
use WPML\TranslationMode\Endpoint\SetAiSkipped;
use WPML\TranslationRoles\UI\Initializer as TranslationRolesInitializer;
use WPML\UIPage;
use WPML_Flags;
use WPML_TM_ATE_AMS_Endpoints;
use WPML_TM_ATE_Status;

use function WPML\Container\make;

class Initializer {
	const SETTINGS_UNRECOVERABLE_ERROR = 'settings_unrecoverable';

	const ATE_DASHBOARD_LOAD_TIMEOUT_MS = 30000;

	public static function loadJS() {
		if ( self::settingsAreUnrecoverable() ) {
			return;
		}

		$setupApp = Resources::enqueueApp( 'setup' );

		// wizard reload after the licence step, with no way to boot it, and
		self::registerAteDashboardScript( false );

		$deps = [];
		if ( wp_script_is( 'wpml-setup-tea', 'registered' ) ) {
			$deps[] = 'wpml-setup-tea';
		}
		$setupApp( self::getData(), $deps );

		self::maybePrefetchAteDashboardScript();
		if ( wp_style_is( 'wpml-setup-tea', 'registered' ) ) {
			wp_enqueue_style( 'wpml-setup-tea' );
		}

		LanguageEditorWizard::enqueue();

		\WPML\TM\ATE\ClonedSites\ReconnectNotice::enqueueComponent();
	}

	public static function getData() {
		if ( self::settingsAreUnrecoverable() ) {
			return self::getQuarantinedData();
		}

		global $wpml_dic;
		$currentStep = Option::getCurrentStep();
		$currentStep = make( SetupMigrationService::class )->maybeMigrateCredentials( $currentStep );

		if (
			\TranslationProxy::has_preferred_translation_service()
			&& in_array( $currentStep, CurrentStep::STEPS_REMOVED_FOR_SERVICE, true )
		) {
			$currentStep = CurrentStep::STEP_AI_TRANSLATION;
		}

		if ( self::isHalfConfigured() && 'languages' !== $currentStep ) {
			$currentStep = 'languages';
			Option::saveCurrentStep( $currentStep );
		}

		$siteUrl = self::getSiteUrl();

		$defaultLang  = self::getDefaultLang();
		$originalLang = Option::getOriginalLang();
		if ( ! $originalLang ) {
			$originalLang = $defaultLang;
			Option::setOriginalLang( $originalLang );
		}

		$userLang = Languages::getUserLanguageCode()->getOrElse( $defaultLang );

		$requirementsService = $wpml_dic->make( RequirementsService::class );

		$networkSiteKey = self::getNetworkSiteKey();
		if ( defined( 'OTGS_INSTALLER_SITE_KEY_WPML' ) ) {
			self::registerSiteKey( self::getPredefinedSiteKey(), self::isPredefinedSiteKeySaved() );
		} elseif ( $networkSiteKey && wpml_get_setting( 'site_key', '' ) !== $networkSiteKey ) {
			self::registerSiteKey( $networkSiteKey );
		}

		self::maybeCaptureWizardStartedEvent( $currentStep );

		$teaPreflight = \WPML\TM\ATE\TranslateEverything\Preflight::collect();

		try {
			$minimumRequirements = $requirementsService->getInvalidRequirements( true );
		} catch ( \Throwable $e ) {
			\WPML\PHP\Logger\error(
				'Failed to get InvalidRequirements: ' . $e->getMessage() . ' ' . $e->getTraceAsString()
			);
			$minimumRequirements = [];
		}

		$data = [
			'name' => 'wpml_wizard',
			'data' => [
				'currentStep'              => $currentStep,
				'minimumRequirements'      => $minimumRequirements,
				'endpoints'                => self::getWizardEndpoints(),
				'languages'                => [
					'list'                  => ActiveLanguages::withLanguageField(
						(array) Obj::values( Languages::withFlags( Languages::getAll( $userLang ) ) )
					),
					'secondaries'           => Fns::map( Languages::getLanguageDetails(), Option::getTranslationLangs() ),
					'original'              => Languages::getLanguageDetails( $originalLang ),
					'customFlagsDir'        => self::getCustomFlagsDir(),
					'predefinedFlagsDir'    => WPML_Flags::get_wpml_flags_url(),
					'flagsByLocalesFileUrl' => WPML_Flags::get_wpml_flags_by_locales_url(),
				],
				'siteAddUrl'               => \WPML\OutboundLinks\OutboundLinks::to(
					'https://app.wpml.org/account/sites?add=' . rawurlencode( self::getRegistrationSiteUrl() ) . '&wpml_version=' . self::getWPMLVersion(),
					array(
						'medium'   => 'wizard',
						'campaign' => 'account',
					)
				),
				'siteKey'                  => self::getSiteKey(),
				'usePredefinedSiteKey'     => self::isPredefinedSiteKeySaved(),
				'useNetworkSiteKey'        => (bool) $networkSiteKey,
				'supportValue'             => OTGS_Installer_WP_Share_Local_Components_Setting::get_setting( 'wpml' ),
				'address'                  => [
					'siteUrl'       => $siteUrl,
					'permalinksUrl' => admin_url( 'options-permalink.php' ),
					'mode'          => self::getLanguageNegotiationMode(),
					'domains'       => LanguageNegotiation::getDomains() ?: [],
					'canUseDomains' => LanguageNegotiation::isDomainModeAvailable(),
					'gotUrlRewrite' => null,
				],
				'isTMAllowed'              => Option::isTMAllowed() === true,
				'isTMDisabled'             => Option::isTMAllowed() === false,
				'isAteEnabled'             => WPML_TM_ATE_Status::is_enabled_and_activated(),
				'isWCMLWizardWaiting'      => ShouldShowWCMLMessages::getOption(),
				'isTranslateEverything'    => Option::getTranslateEverything(),
				'isAiSkipped'              => Option::getAiSkipped(),
				'ateBaseUrl'               => self::getATEBaseUrl(),
				'ateDashboardScript'       => make( ATEDashboardLoader::class )->getRegisteredScriptUrl(),
				'ateDashboard'             => self::getAteDashboardData(),
				'whenFinishedUrlLanguages' => admin_url( UIPage::getLanguageSwitchers() ),
				'whenFinishedUrlTM'        => admin_url( UIPage::getTM() ),
				'ateSignUpUrl'             => admin_url( UIPage::getTMATE() ),
				'languagesMenuUrl'         => admin_url( UIPage::getLanguages() ),
				'postsListingUrl'          => admin_url( 'edit.php' ),
				'pagesListingUrl'          => admin_url( 'edit.php?post_type=page' ),
				'WCMLWizardUrl'            => admin_url( 'index.php?page=wcml-setup' ),
				'adminUserName'            => User::getCurrent()->display_name,
				'wpmlSupportPage'          => admin_url( 'admin.php?page=sitepress-multilingual-cms/menu/support.php' ),
				'resetSetup'               => [
					'ajaxUrl' => \admin_url( 'admin-ajax.php' ),
					'nonce'   => \wp_create_nonce( 'reset_wpml_wizard' ),
				],
				'translation'              => Lst::concat(
					TranslationMethodSettings::getModeSettingsData(),
					TranslationRolesInitializer::getTranslationData( null, false )
				),

				'license'                  => [
					'actions'  => [
						'registerSiteKey' => Endpoint\LicenseStep::ACTION_REGISTER_SITE_KEY,
						'getSiteType'     => Endpoint\LicenseStep::ACTION_GET_SITE_TYPE,
					],
					'siteType' => [
						'production'  => OTGS_Installer_Subscription::SITE_KEY_TYPE_PRODUCTION,
						'development' => OTGS_Installer_Subscription::SITE_KEY_TYPE_DEVELOPMENT,
					],
				],
			],
		];

		if ( $teaPreflight ) {
			$data['data']['teaPreflight'] = $teaPreflight;
		}

		return $data;
	}

	public static function shouldBlockMutatingEndpoint( $endpoint, Collection $data ) {
		if ( ! self::settingsAreUnrecoverable() ) {
			return false;
		}
		if ( ! is_string( $endpoint ) ) {
			return true;
		}

		if ( Endpoint\LicenseStep::class === $endpoint ) {
			return Endpoint\LicenseStep::ACTION_GET_SITE_TYPE !== $data->get( 'action' );
		}

		$read_only = array(
			Endpoint\ATEDashboardScript::class,
			Endpoint\GetLanguagesAutomaticSupport::class,
			Endpoint\GetParametersForAteDashboard::class,
			Endpoint\RecommendedPlugins::class,
			Endpoint\ShouldShowWCMLMessages::class,
			Endpoint\TranslationServices::class,
			\WPML\TranslationRoles\FindAvailableByRole::class,
			\WPML\TranslationRoles\GetManagerRecords::class,
			\WPML\TranslationRoles\GetTranslatorRecords::class,
			\WPML\LanguageEditor\Endpoint\AddLanguageTranslationInfo::class,
			\WPML\LanguageEditor\Endpoint\CountLanguageContent::class,
			\WPML\LanguageEditor\Endpoint\GetAutomaticTranslationInfo::class,
			\WPML\LanguageEditor\Endpoint\GetLanguageLabels::class,
			\WPML\LanguageEditor\Save\Endpoint\EstimateImpact::class,
			\WPML\LanguageEditor\Save\Endpoint\SaveStatus::class,
		);

		return ! in_array( $endpoint, $read_only, true );
	}

	public static function getSettingsRecoveryError() {
		return array(
			'code'    => self::SETTINGS_UNRECOVERABLE_ERROR,
			'message' => __( 'WPML setup cannot be changed because the stored settings could not be recovered. Restore the settings backup or contact WPML support.', 'sitepress' ),
		);
	}

	public static function rejectSettingsMutationAjax() {
		if ( ! self::settingsAreUnrecoverable() ) {
			return false;
		}

		wp_send_json_error( self::getSettingsRecoveryError() );

		return true;
	}

	public static function rejectQuarantinedWpmlRestRequest( $response, $handler, $request ) {
		unset( $handler );
		if ( null !== $response || ! self::settingsAreUnrecoverable() || ! is_object( $request ) || ! method_exists( $request, 'get_route' ) ) {
			return $response;
		}

		$route = (string) $request->get_route();
		if ( ! preg_match( '#^/wpml(?:/|$)#', $route ) ) {
			return $response;
		}

		$error = self::getSettingsRecoveryError();

		return new \WP_Error( $error['code'], $error['message'], array( 'status' => 409 ) );
	}

	public static function rejectQuarantinedWpmlAjaxRequest() {
		if ( ! wp_doing_ajax() || ! self::settingsAreUnrecoverable() ) {
			return false;
		}

		$action = isset( $_REQUEST['action'] ) && is_scalar( $_REQUEST['action'] )
			? (string) $_REQUEST['action']
			: '';
		if ( 'wpml_action' === $action ) {
			return false;
		}
		if ( ! self::isWpmlAjaxAction( $action ) ) {
			return false;
		}

		wp_send_json_error( self::getSettingsRecoveryError(), 409 );

		return true;
	}

	public static function rejectQuarantinedWpmlAjaxRequestOnAdminInit() {
		self::rejectQuarantinedWpmlAjaxRequest();
	}

	private static function isWpmlAjaxAction( $action ) {
		foreach ( array( 'wpml', 'icl', 'otgs' ) as $prefix ) {
			if ( 0 === stripos( $action, $prefix ) ) {
				return true;
			}
		}

		if (
			in_array(
				$action,
				array(
					'translation_service_toggle',
					'refresh_ts_info',
					'translation_service_authentication',
					'translation_service_update_credentials',
					'translation_service_enable_unlisted_service',
					'translation_service_invalidation',
					'save_notification_settings',
					'save_language_negotiation_type',
					'set_xliff_options',
				),
				true
			)
		) {
			return true;
		}

		global $wp_filter;
		foreach ( array( 'wp_ajax_' . $action, 'wp_ajax_nopriv_' . $action ) as $hook ) {
			if ( ! isset( $wp_filter[ $hook ] ) || ! is_object( $wp_filter[ $hook ] ) || ! isset( $wp_filter[ $hook ]->callbacks ) ) {
				continue;
			}
			foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( isset( $callback['function'] ) && self::callbackBelongsToWpml( $callback['function'] ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	private static function callbackBelongsToWpml( $callback ) {
		try {
			if ( is_array( $callback ) && 2 === count( $callback ) ) {
				$reflection = new \ReflectionMethod( $callback[0], $callback[1] );
			} elseif ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
				$reflection = new \ReflectionMethod( $callback );
			} else {
				$reflection = new \ReflectionFunction( $callback );
			}
			$file = $reflection->getFileName();
		} catch ( \Throwable $e ) {
			return false;
		}

		return is_string( $file )
			&& 0 === strpos( wp_normalize_path( $file ), trailingslashit( wp_normalize_path( WPML_PLUGIN_PATH ) ) );
	}

	public static function settingsAreUnrecoverable() {
		return class_exists( '\\WPML_Settings_Failsafe_Loader', false )
			&& \WPML_Settings_Failsafe_Loader::isUnrecoverable();
	}

	private static function getQuarantinedData() {
		return array(
			'name' => 'wpml_wizard',
			'data' => array(
				'endpoints'               => array(),
				'isSettingsUnrecoverable' => true,
				'settingsRecoveryError'   => self::getSettingsRecoveryError(),
			),
		);
	}

	private static function getWizardEndpoints() {
		return Lst::concat(
			array(
				'setOriginalLanguage'          => Endpoint\SetOriginalLanguage::class,
				'setSupport'                   => Endpoint\SetSupport::class,
				'setSecondaryLanguages'        => Endpoint\SetSecondaryLanguages::class,
				'currentStep'                  => Endpoint\CurrentStep::class,
				'addressStep'                  => Endpoint\AddressStep::class,
				'licenseStep'                  => Endpoint\LicenseStep::class,
				'aiTranslation'                => Endpoint\AITranslationStep::class,
				'translationStep'              => Endpoint\TranslationStep::class,
				'setReviewMode'                => SetReviewMode::class,
				'setTranslateEverything'       => SetTranslateEverything::class,
				'setAiSkipped'                 => SetAiSkipped::class,
				'recommendedPlugins'           => Endpoint\RecommendedPlugins::class,
				'finishStep'                   => Endpoint\FinishStep::class,
				'finishMediaSetup'             => Endpoint\FinishMediaSetup::class,
				'finishDeferredUpgrade'        => Endpoint\FinishDeferredUpgrade::class,
				'addLanguages'                 => Endpoint\AddLanguages::class,
				'verifyUrlRewrite'             => Endpoint\VerifyUrlRewrite::class,
				'getLanguagesAutomaticSupport' => Endpoint\GetLanguagesAutomaticSupport::class,
				'upload'                       => Upload::class,
				'checkTMAllowed'               => CheckTMAllowed::class,
				'shouldShowWCMLMessages'       => ShouldShowWCMLMessages::class,
				'ateDashboardScript'           => Endpoint\ATEDashboardScript::class,
				'getParametersForAteDashboard' => Endpoint\GetParametersForAteDashboard::class,
				'enableATE'                    => Endpoint\EnableAte::class,
			),
			TranslationRolesInitializer::getEndPoints()
		);
	}


	private static function isHalfConfigured() {
		global $sitepress;

		return $sitepress->is_setup_complete()
			&& 2 > count( $sitepress->get_active_languages() );
	}

	private static function maybeCaptureWizardStartedEvent( $currentStep ) {
		$wizardUUID = get_option( 'wpml_ph_wizard_uuid', false );

		if ( 'languages' === $currentStep && ! $wizardUUID ) {
			$event = ( new EventInstanceService() )->getWizardStartedEvent( [] );
			CaptureWizardStartedEvent::capture( $event );
		}
	}


	private static function registerAteDashboardScript( $enqueue = true ) {
		$ateDashboardLoader = make( ATEDashboardLoader::class );

		return $ateDashboardLoader->registerScript( $enqueue );
	}

	private static function maybePrefetchAteDashboardScript() {
		if ( ! self::shouldPrefetchAteDashboardScript() ) {
			return;
		}

		$url = make( ATEDashboardLoader::class )->getRegisteredScriptUrl();
		if ( ! $url ) {
			return;
		}

		printf( '<link rel="prefetch" as="script" href="%s">', esc_url( $url ) );
	}

	public static function shouldPrefetchAteDashboardScript() {
		if ( false === Option::isTMAllowed() ) {
			return false;
		}

		return ! make( ProxyInterceptorLoader::class )->shouldEnableProxy();
	}

	/**
	 * Everything the browser needs to load and boot the AMS dashboard script on its
	 * own, once the licence key makes ATE usable (wpmldev-8616).
	 *
	 * @return array<string,mixed>
	 */
	private static function getAteDashboardData() {
		$loader    = make( ATEDashboardLoader::class );
		$proxy     = make( ProxyInterceptorLoader::class );
		$isProxy   = $proxy->shouldEnableProxy();
		$scriptUrl = (string) $loader->getRegisteredScriptUrl();

		return [
			'scriptUrl'     => $scriptUrl,
			'isProxy'       => $isProxy,
			'loadTimeoutMs' => self::ATE_DASHBOARD_LOAD_TIMEOUT_MS,
			'proxy'         => [
				'scriptUrl'          => $isProxy ? $scriptUrl : $proxy->getProxyUrlWithNonce( $scriptUrl ),
				'interceptorSrc'     => $proxy->getInterceptorSrc(),
				'interceptorOptions' => $proxy->getInterceptorOptions(),
				'statusEndpoint'     => [
					'url'   => rest_url( 'wpml/v1/wpml-proxy/status' ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
				],
			],
		];
	}


	private static function isPredefinedSiteKeySaved() {
		$predefinedKey = self::getPredefinedSiteKey();

		return function_exists( 'OTGS_Installer' )
				&& '' !== $predefinedKey
				&& OTGS_Installer()->get_site_key( 'wpml' ) === $predefinedKey;
	}

	private static function getPredefinedSiteKey() {
		return defined( 'OTGS_INSTALLER_SITE_KEY_WPML' )
			? (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) OTGS_INSTALLER_SITE_KEY_WPML )
			: '';
	}

	/**
	 * The key the network holds, when this subsite reads it from the Installer (wpmldev-10302).
	 * One license covers the network: a subsite uses the key the main site registered, and its
	 * wizard skips the license step as it does for a key predefined in wp-config.php.
	 *
	 * @return string|null
	 */
	private static function getNetworkSiteKey() {
		global $sitepress;
		$wpApi = $sitepress->get_wp_api();

		if ( ! $wpApi->is_multisite() || $wpApi->is_main_site() || ! function_exists( 'OTGS_Installer' ) ) {
			return null;
		}

		$networkKey = Obj::pathOr( null, [ 'wpml', 'key' ], (array) maybe_unserialize( get_site_option( 'wp_installer_network' ) ) );

		return $networkKey && OTGS_Installer()->get_site_key( 'wpml' ) === $networkKey ? $networkKey : null;
	}

	private static function registerSiteKey( $siteKey, $alreadySaved = false ) {
		if ( ! User::hasCap( 'wpml_manage_languages' ) ) {
			return;
		}

		if ( function_exists( 'OTGS_Installer' ) ) {
			$args   = [
				'repository_id' => 'wpml',
				'nonce'         => \wp_create_nonce( 'save_site_key_wpml' ),
				'site_key'      => $siteKey,
				'return'        => 1,
			];
			$result = $alreadySaved ? [ 'error' => '' ] : OTGS_Installer()->save_site_key( $args );
			if ( empty( $result['error'] ) ) {
				icl_set_setting( 'site_key', $siteKey, true );

				if ( Plugins::updateTMAllowedOption() && ! WPML_TM_ATE_Status::is_enabled_and_activated() ) {
					Option::setTranslateEverythingDefault();
					make( EnableATE::class )->enable();
				}
			}
		}
	}

	private static function getLanguageNegotiationMode() {
		$mode = LanguageNegotiation::getModeAsString();

		if ( LanguageNegotiation::DOMAIN_STRING === $mode && ! LanguageNegotiation::isDomainModeAvailable() ) {
			return LanguageNegotiation::DIRECTORY_STRING;
		}

		return $mode;
	}

	private static function getDefaultLang() {
		$getLangFromConstant = function () {
			global $sitepress;

			return Maybe::fromNullable( $sitepress->get_wp_api()->constant( 'WP_LANG' ) )
			            ->map( Languages::localeToCode() )
			            ->getOrElse( 'en' );
		};

		return Maybe::fromNullable( WPOption::getOr( 'WPLANG', null ) )
		            ->map( Languages::localeToCode() )
		            ->getOrElse( $getLangFromConstant );
	}

	private static function getCustomFlagsDir() {
		return sprintf( '%s/flags/', Obj::propOr( '', 'baseurl', wp_upload_dir() ) );
	}

	private static function getATEBaseUrl() {
		return make( WPML_TM_ATE_AMS_Endpoints::class )->get_base_url( WPML_TM_ATE_AMS_Endpoints::SERVICE_ATE );
	}

	private static function getWPMLVersion() {
		return Obj::prop( 'Version', get_plugin_data( WPML_PLUGIN_PATH . '/' . WPML_PLUGIN_FILE ) );
	}

	private static function getSiteKey() {
		$installerKey = (string) OTGS_Installer()->get_site_key( 'wpml' );
		$siteKey      = '' !== $installerKey ? $installerKey : wpml_get_setting( 'site_key', '' );

		return is_string( $siteKey ) && strlen( $siteKey ) === 10 ? $siteKey : '';
	}

	private static function getSiteUrl() {
		return OTGS_Installer()->get_installer_site_url();
	}

	private static function getRegistrationSiteUrl() {
		return OTGS_Installer()->get_installer_site_url( 'wpml' );
	}
}
