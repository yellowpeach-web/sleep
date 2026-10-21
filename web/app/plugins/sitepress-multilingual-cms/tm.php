<?php

use WPML\TM\Settings\CustomFieldChangeDetector;

if ( defined( 'WPML_TM_VERSION' ) || get_option( '_wpml_inactive' ) ) {
	return;
}

define( 'WPML_TM_VERSION', '2.11.0' );

if ( ! defined( 'WPML_TM_PATH' ) ) {
	define( 'WPML_TM_PATH', dirname( __FILE__ ) );
}

function initialize_wpml_cache_factory() {
	global $wpml_cache_factory;

	$translator_filters = [ 'wpml_tm_add_translation_role', 'wpml_tm_remove_translation_role' ];
	$wpml_cache_factory->define( 'WPML_TM_Blog_Translators::get_raw_blog_translators', $translator_filters );

	$wpml_cache_factory->define( 'WPML_TM_Blog_Translators::has_translators', $translator_filters );
}

function wpml_tm_load( $sitepress = null ) {
	global $wpdb, $WPML_Translation_Management, $ICL_Pro_Translation;

	$is_admin   = is_admin();
	$is_xmlrpc  = defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST;
	$is_rest    = wpml_is_rest_request();
	$is_cron    = defined( 'DOING_CRON' ) && DOING_CRON;
	$is_backend = $is_admin || $is_rest || $is_xmlrpc || wpml_is_cli() || $is_cron;

	require_once WPML_TM_PATH . '/inc/constants-tm.php';
	require_once WPML_TM_PATH . '/inc/functions-load.php';
	if ( $is_backend ) {
		require_once WPML_TM_PATH . '/inc/translation-proxy/wpml-pro-translation.class.php';
		require_once WPML_TM_PATH . '/inc/js-tm-scripts.php';
		require_once WPML_TM_PATH . '/inc/deprecated-hooks.php';
	}

	new WPML_TM_Requirements();

	if ( ! $sitepress ) {
		global $sitepress;
	}

	\WPML\Container\share( \WPML\TM\Container\Config::getSharedClasses() );
	\WPML\Container\delegate( \WPML\TM\Container\Config::getDelegated() );

	\WPML\Container\make( \WPML_Translator_Records::class );
	\WPML\Container\make( \WPML_Translation_Manager_Records::class );

	if ( ! $sitepress || ! $sitepress instanceof SitePress || ! $sitepress->is_setup_complete() ) {
		return;
	}

	$is_plain_cli = wpml_is_cli() && ! $is_admin;

	if ( $is_backend && ! $is_plain_cli ) {
		require_once WPML_TM_PATH . '/menu/wpml-tm-menus.class.php';
	}

	$action_filter_loader = new WPML_Action_Filter_Loader();

	if ( $is_backend ) {
		if ( $is_plain_cli ) {
			wpml_tm_load_cli();
		} else {
			$WPML_Translation_Management = wpml_translation_management();
			$WPML_Translation_Management->init();
			$WPML_Translation_Management->load();
		}

		if ( ! $ICL_Pro_Translation ) {
			$job_factory         = wpml_tm_load_job_factory();
			$ICL_Pro_Translation = new WPML_Pro_Translation( $job_factory );
		}
	} else {
		do_action( 'wpml_tm_loaded' );
	}

	if ( $is_admin ) {
		if ( defined( 'DOING_AJAX' ) ) {
			$wpml_tm_options_ajax = new WPML_TM_Options_Ajax( $sitepress );
			$wpml_tm_options_ajax->ajax_hooks();

			$wpml_tm_pickup_mode_ajax = new WPML_TM_Pickup_Mode_Ajax( $sitepress, $ICL_Pro_Translation );
			$wpml_tm_pickup_mode_ajax->ajax_hooks();
		}
	}

	$global_actions = [
		'WPML_TM_API_Hooks_Factory',
		'WPML_TM_ATE_Translator_Login_Factory',
		'WPML_TM_Shortcodes_Catcher_Factory',
		'\WPML\TM\AdminBar\Hooks',
		\WPML\TM\AutomaticTranslation\Actions\ActionsFactory::class,
		\WPML\TM\Jobs\EditAccess\AllowPairTranslatorsToEdit::class,
		\WPML\TM\ATE\Review\ReviewTranslation::class,
		\WPML\TM\ATE\Hooks\JobActionsFactory::class,
		'WPML_TM_ATE_Jobs_Actions_Factory',
		\WPML\TM\ATE\UpdateTranslation\UpdateTranslationFrontend::class,
		'\WPML\ATE\Proxies\Widget',
		'WPML_TM_Upgrade_Loader_Factory',
		\WPML\TM\AutomaticTranslation\Actions\AutomaticTranslationJobCreationFailureNoticeFactory::class,
		\WPML\ATE\Proxies\Dashboard::class,
		\WPML\TM\ATE\ClonedSites\AliasDomainCheckHandler::class,
		\WPML\TM\ATE\Release\HooksFactory::class,
		\WPML\TM\ATE\Release\BlockEditorNoticeFactory::class,
		\WPML\TM\ATE\PullDelivery\Hooks::class,
		\WPML\TM\ATE\SiteName\Hooks::class,
	];
	$action_filter_loader->load( $global_actions );

	if ( $is_backend ) {
		$actions = [
			'WPML_TM_Jobs_Deadline_Cron_Hooks_Factory',
			'WPML_TM_Emails_Settings_Factory',
			'WPML_TM_Jobs_Summary_Report_Hooks_Factory',
			\WPML\TM\Menu\TranslationServices\Resources::class,
			\WPML\TM\Menu\TranslationServices\ActivationAjaxFactory::class,
			\WPML\TM\Menu\TranslationServices\AuthenticationAjaxFactory::class,
			\WPML\TM\Menu\TranslationServices\Troubleshooting\RefreshServicesFactory::class,
			'WPML_TP_Lock_Notice_Factory',
			'WPML_TM_Admin_Menus_Factory',
			'WPML_TM_Privacy_Content_Factory',
			'WPML_TM_Serialized_Custom_Field_Package_Handler_Factory',
			'WPML_TM_MCS_Pagination_Ajax_Factory',
			'WPML_Translation_Jobs_Migration_Hooks_Factory',
			'WPML_TM_Only_I_Language_Pairs',
			'WPML_TM_Post_Edit_TM_Editor_Select_Factory',
			'WPML_TM_Translation_Jobs_Fix_Summary_Factory',
			'WPML_TM_Reset_Options_Filter_Factory',
			\WPML\TM\User\Hooks::class,
			\WPML\TM\Jobs\ExtraFieldDataInEditorFactory::class,
			\WPML\TM\ATE\Sitekey\DirectSync::class,
			\WPML\TM\ATE\Sitekey\Sync::class,
			\WPML\TM\ATE\Sitekey\UnassignDirectSync::class,
			\WPML\TM\ATE\Sitekey\UnassignSync::class,
			\WPML\TM\ATE\Review\ReviewCompletedNotice::class,
			\WPML\TM\Settings\CustomFieldChangeDetector::class,
			\WPML\TM\Settings\ChecksumFieldHistory::class,
			\WPML\MediaTranslation\AddMediaDataToTranslationPackageFactory::class,
			\WPML\MediaTranslation\ExcludeTranslatedMediaFromAutomaticJobFactory::class,
			\WPML\MediaTranslation\MediaTranslationEditorLayoutFactory::class,
			\WPML\MediaTranslation\MediaTranslationStatusFactory::class,
			\WPML\TranslationManagement\Dashboard\Loader::class,
			\WPML\TM\Jobs\BaselineJobHooks::class,
			\WPML\TranslationMode\ClearAiSkippedOnAiSettings::class,
		];
		$action_filter_loader->load( $actions );

		$rest_actions = [
			'WPML_TM_REST_ATE_Jobs_Factory',
			'WPML_TM_REST_XLIFF_Factory',
			'WPML_TM_REST_AMS_Clients_Factory',
			'WPML_TM_REST_ATE_API_Factory',
			'WPML_TM_REST_Jobs_Factory',
			'WPML_TM_REST_TP_XLIFF_Factory',
			'WPML_TM_REST_Apply_TP_Translation_Factory',
			'WPML_TM_REST_Batch_Sync_Factory',
			\WPML\TM\REST\FactoryLoader::class,
			\WPML\TM\ATE\Factories\Proxy::class,
		];
		$action_filter_loader->load( $rest_actions );

		$ams_ate_actions = [
			'WPML_TM_AMS_Synchronize_Actions_Factory',
			'WPML_TM_ATE_Jobs_Store_Actions_Factory',
			'WPML_TM_ATE_Job_Data_Fallback_Factory',
			'WPML_TM_ATE_Post_Edit_Actions_Factory',
			'WPML_TM_Old_Editor_Factory',
			\WPML\TM\ATE\Log\Hooks::class,
			\WPML\TM\Jobs\Log\Hooks::class,
			\WPML\TM\ATE\Hooks\ReturnedJobActionsFactory::class,
			\WPML\TM\ATE\Loader::class,
			\WPML\TM\Jobs\Loader::class,
			\WPML\TM\ATE\Review\ApplyJob::class,
			\WPML\TM\ATE\Review\PackageJob::class,
			\WPML\TM\ATE\Review\StatusIcons::class,
			\WPML\TM\ATE\Review\GhostTranslationCleanup::class,
			\WPML\TM\ATE\StatusIcons::class,
			\WPML\TM\Editor\ManualJobCreationErrorNotice::class,
			\WPML\Support\ATE\Hooks::class,
			\WPML\Support\TmJobs\Hooks::class,
			\WPML\TM\ATE\AutoTranslate\Hooks\JobsCountCacheInvalidateAction::class,
			\WPML\TM\ATE\AutoTranslate\Hooks\AccountCacheInvalidateAction::class,
			\WPML\TM\ATE\Retranslation\BackgroundTask\ProgressHook::class,
			\WPML\TranslationManagement\Dashboard\Loader::class,
		];
		$action_filter_loader->load( $ams_ate_actions );
	}

	do_action( 'wpml_after_tm_loaded' );

	if ( $is_admin ) {
		add_filter( 'wpml_support_info_blocks', 'wpml_tm_support_info' );
	}

	initialize_wpml_cache_factory();
}

add_action( 'wpml_loaded', 'wpml_tm_load', 10, 1 );

function wpml_tm_support_info( array $blocks ) {
	$support_info = new WPML_TM_Support_Info();

	$ui = new WPML_TM_Support_Info_Filter( $support_info );

	return $ui->filter_blocks( $blocks );
}


function wpml_tm_reset_user_options( array $options ) {
	$options[] = WPML_TM_Menus_Management::SKIP_TM_WIZARD_META_KEY;

	return $options;
}

if ( is_admin() ) {
	add_filter( 'wpml_reset_user_options', 'wpml_tm_reset_user_options' );
}

if ( is_admin() && !wpml_is_ajax() ) {
	$customFieldChangeDetector = \WPML\Container\make( CustomFieldChangeDetector::class );
	$customFieldChangeDetector->processNewFields();
}

add_action( 'added_post_meta', array( CustomFieldChangeDetector::class, 'onPostMetaWritten' ), 10, 3 );
add_action( 'deleted_post_meta', array( CustomFieldChangeDetector::class, 'onPostMetaWritten' ), 10, 3 );
add_action( 'wpml_translation_update', array( CustomFieldChangeDetector::class, 'forgetPending' ) );

add_filter(
	\WPML\TM\Settings\PreferenceSource::FILTER,
	array( \WPML\TM\Settings\PreferenceSource::class, 'filter' ),
	10,
	3
);

add_filter(
	\WPML\TM\ATE\TranslateEverything\Preflight::ADVISORIES_FILTER,
	array( \WPML\TM\ATE\TranslateEverything\Preflight::class, 'advisories' )
);
