<?php

namespace WPML\Troubleshooting;

use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\IncidentStore;
use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\MigrationStateStorage;
use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\Repository\PreferenceRepository;
use WPML\Knowledge\ElementKnowledge;
use WPML\Media\Lookup\MediaLookupSchema;
use WPML\TM\ATE\ClonedSites\AliasDomainResetFlag;
use WPML\TM\ATE\ClonedSites\ReconnectState;
use WPML\TM\ATE\JobCreation\AteCreateReconciler;
use WPML\TM\Emails\Report\JobRowsStorage;
use WPML\TM\Settings\ChecksumFieldHistory;
use WPML_Cache_Directory;
use WPML_Config_Update;
use WPML_WP_API;
use WP_User;

class ResetService {
	const SETTINGS_RESET_STARTED  = 'wpml_sitepress_settings_reset_started';
	const SETTINGS_RESET_FINISHED = 'wpml_sitepress_settings_reset_finished';

	const ICL_TABLES = array(
		'icl_languages',
		'icl_languages_translations',
		'icl_translations',
		'icl_translation_status',
		'icl_translate_job',
		'icl_translate_unsolvable_jobs',
		'icl_translate',
		'icl_locale_map',
		'icl_flags',
		'icl_content_status',
		'icl_core_status',
		'icl_node',
		'icl_strings',
		'icl_string_packages',
		'icl_translation_batches',
		'icl_string_translations',
		'icl_string_status',
		'icl_string_positions',
		'icl_message_status',
		'icl_reminders',
		'icl_mo_files_domains',
		'icl_string_pages',
		'icl_string_urls',
		'icl_url_resolution_cache',
		'icl_cms_nav_cache',
		'icl_string_batches',
		'icl_translation_downloads',
		'icl_background_task',
		PreferenceRepository::TABLE,
		'icl_links_post_to_post',
		'icl_links_post_to_term',
		'icl_countries',
		'icl_countries_translations',
		'icl_language_presets',
		'icl_language_preset_countries',
		ElementKnowledge::TABLE,
		MediaLookupSchema::TABLE,
	);

	const WPML_USER_OPTIONS = array(
		'language_pairs',
	);

	const POST_DEACTIVATION_OPTIONS = array(
		'wpml_dependencies:needs_validation',
		'wpml_dependencies:valid_plugins',
		'wpml_dependencies:invalid_plugins',
		'wpml_dependencies:fingerprint',
		'wpml_dependencies:required_by',
		'wpml_dependencies:plugin_names',
	);

	const RESET_CAPABILITIES = array(
		'wpml_manage_translation_management',
		'wpml_manage_languages',
		'wpml_manage_theme_and_plugin_localization',
		'wpml_manage_support',
		'wpml_manage_woocommerce_multilingual',
		'wpml_operate_woocommerce_multilingual',
		'wpml_manage_media_translation',
		'wpml_manage_navigation',
		'wpml_manage_sticky_links',
		'wpml_manage_string_translation',
		'wpml_manage_translation_analytics',
		'wpml_manage_wp_menus_sync',
		'wpml_manage_taxonomy_translation',
		'wpml_manage_translation_options',
		'manage_translations',
		'translate',
	);

	private $generated_translation_files;

	public function __construct( ?GeneratedTranslationFiles $generated_translation_files = null ) {
		$this->generated_translation_files = $generated_translation_files ?: new GeneratedTranslationFiles();
	}

	public function run( $blog_id = false ) {
		global $wpdb, $sitepress_settings;

		$this->verify_admin_referer_if_needed();

		if ( empty( $blog_id ) ) {
			$blog_id = $this->resolve_blog_id();
		}

		if ( ! $this->should_reset( $blog_id ) ) {
			return;
		}

		$switched_blog = false;
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			switch_to_blog( (int) $blog_id );
			$switched_blog = true;
		}

		$destructive_reset_started = false;
		$settings_reset_completed  = false;
		$reset_window_open         = false;
		try {
			ResetWindow::open();
			$reset_window_open = true;
			ResetWindow::drain();

			\WPML_Settings_Failsafe_Loader::beginDestructiveReset();
			$destructive_reset_started = true;

			do_action( 'wpml_reset_plugins_before' );

			wp_clear_scheduled_hook( 'update_wpml_config_index' );

			$this->drop_tables( $wpdb, $blog_id );
			do_action( self::SETTINGS_RESET_STARTED, (int) $blog_id );
			try {
				$this->delete_options( $blog_id );
				$settings_reset_completed = true;
			} finally {
				do_action( self::SETTINGS_RESET_FINISHED, (int) $blog_id );
			}
			$this->delete_user_options( $wpdb, $blog_id );
			$this->reset_user_capabilities( $blog_id );

			$sitepress_settings = null;
			wp_cache_init();

			( new WPML_Cache_Directory( new WPML_WP_API() ) )->remove();
			$this->generated_translation_files->remove( $switched_blog ? (int) $blog_id : 1 );

			do_action( 'wpml_reset_plugins_after' );

			$this->detach_deactivation_recommendation_listener();
			$this->deactivate_or_mark_inactive();

			$this->delete_post_deactivation_options( $blog_id );
		} finally {
			try {
				try {
					if ( $destructive_reset_started && $settings_reset_completed && ! \WPML_Settings_Failsafe_Loader::finishDestructiveReset() ) {
						throw new \RuntimeException( 'WPML could not verify that its protected settings were deleted during reset.' );
					}
				} finally {
					if ( $reset_window_open ) {
						ResetWindow::close();
					}
				}
			} finally {
				if ( $switched_blog ) {
					restore_current_blog();
				}
			}
		}
	}

	private function verify_admin_referer_if_needed() {
		if ( isset( $_REQUEST['action'] ) && 'resetwpml' === $_REQUEST['action'] ) {
			check_admin_referer( 'resetwpml' );
		}
	}

	private function resolve_blog_id() {
		global $wpdb;

		$filtered_id = filter_input( INPUT_POST, 'id', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );
		$filtered_id = $filtered_id ? $filtered_id : filter_input( INPUT_GET, 'id', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );

		return false !== $filtered_id ? $filtered_id : $wpdb->blogid;
	}

	private function should_reset( $blog_id ) {
		return $blog_id || ! function_exists( 'is_multisite' ) || ! is_multisite();
	}

	private function drop_tables( $wpdb, $blog_id ) {
		$tables = array();
		foreach ( self::ICL_TABLES as $table ) {
			$tables[] = $wpdb->prefix . $table;
		}

		$tables = apply_filters( 'wpml_reset_tables', $tables, $blog_id );

		foreach ( $tables as $table ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . $table );
		}
	}

	private function delete_options( $blog_id ) {
		if ( function_exists( 'wpml_language_cache_rotate_epoch' ) ) {
			\wpml_language_cache_rotate_epoch();
		}

		$options = array(
			'icl_sitepress_settings',
			'icl_sitepress_version',
			'_icl_cache',
			'_icl_cache_language_details',
			'_icl_cache_language_details_epoch',
			'_icl_admin_option_names',
			'wp_icl_translators_cached',
			'wpml32_icl_non_translators_cached',
			'wpml-package-translation-db-updates-run',
			'wpml-package-translation-refresh-required',
			'wpml-package-translation-string-packages-table-updated',
			'wpml-package-translation-string-table-updated',
			'icl_translation_jobs_basket',
			'wpml_tp_batch_state',
			'wpml_tp_project_history',
			'wpml_tp_project_history_blocked',
			\WPML_Settings_Failsafe_Loader::STATE_OPTION,
			'WPML(' . \WPML_Settings_Failsafe_Loader::STATE_GROUP . '/' . \WPML_Settings_Failsafe_Loader::STATE_KEY . ')',
			'WPML(' . \WPML_Settings_Failsafe_Loader::STATE_GROUP . ')',
			'widget_icl_lang_sel_widget',
			'wpml_automatic_service_selection_failed',
			'icl_adl_settings',
			'wpml_tp_com_log',
			'wpml_config_index',
			'wpml_config_index_updated',
			'wpml_config_files_arr',
			WPML_Config_Update::OPTION_KEY_GLOBAL_NOTICES_CONFIG,
			WPML_Config_Update::OPTION_KEY_IMPORT_STATE,
			'wpml_language_switcher',
			'wpml_notices',
			'wpml_start_version',
			'wpml_dependencies:installed_plugins',
			'wpml_dependencies:fingerprint',
			'wpml_dependencies:required_by',
			'wpml_dependencies:plugin_names',
			'wpml_translation_services',
			'wpml_update_statuses',
			\WPML\Upgrade\SchemaFacts::OPTION,
			'wpml_requirements',
			'wpml_rest_api_status',
			'wpml_requirements_refresh_lock',
			'_wpml_dismissed_notices',
			'wpml_translation_services_timestamp',
			'wpml_string_table_ok_for_mo_import',
			'wpml-charset-validation',
			'_wpml_media',
			'wpml_st_display_strings_scan_notices',
			'wpml-st-all-strings-are-in-english',
			'wpml_strings_need_links_fixed',
			'_wpml_batch_report',
			'_wpml_jobs_not_notified',
			'wpml_cms_nav_settings',
			'wpml_cms_nav_cache_table_ready',
			'WPML_CMS_NAV_VERSION',
			'icl_st_settings',
			'wpml_pb_has_shortcode_settings',
			'wpml_pb_block_default_strings',
			'wpml-tm-custom-xml',
			'wpml-st-persist-errors',
			'wpml_base_slug_translation',
			'wpml_ate_auto_migration_data',
			'wpml_ate_auto_migration_failed',
			'wpml_ate_auto_migration_notice_url',
			ReconnectState::OPTION,
			AliasDomainResetFlag::OPTION,
			MigrationStateStorage::MIGRATED_FLAG,
			MigrationStateStorage::STARTED_FLAG,
			MigrationStateStorage::CUTOVER_DEADLINE,
			IncidentStore::OPTION_NAME,
			ChecksumFieldHistory::OPTION,
			ChecksumFieldHistory::CHECK_OPTION,
		);

		$options   = array_merge( $options, \WPML\Options\Reset::get_registered_options() );
		$options[] = 'WPML_Group_Keys';

		$options = apply_filters( 'wpml_reset_options', $options, $blog_id );
		$options = array_unique( $options );

		foreach ( $options as $option ) {
			if ( \WPML_Settings_Failsafe_Loader::OPTION === $option ) {
				\WPML_Settings_Failsafe_Loader::deleteSettingsForDestructiveReset();
				continue;
			}
			delete_option( $option );
		}

		global $wpdb;
		( new JobRowsStorage( $wpdb ) )->deleteAll();

		foreach ( [ AteCreateReconciler::UNCONFIRMED_OPTION_PREFIX, AteCreateReconciler::IN_FLIGHT_OPTION_PREFIX ] as $prefix ) {
			$names = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
					$wpdb->esc_like( $prefix ) . '%'
				)
			);
			foreach ( (array) $names as $name ) {
				delete_option( $name );
			}
		}

		if ( function_exists( 'wpml_language_cache_delete_shards' ) ) {
			\wpml_language_cache_delete_shards();
		}

		foreach ( \WPML\Notices\NoticeStores::rows( $wpdb ) as $notice_row ) {
			delete_option( $notice_row );
		}
	}

	private function delete_user_options( $wpdb, $blog_id ) {
		$user_options = apply_filters( 'wpml_reset_user_options', self::WPML_USER_OPTIONS, $blog_id );
		if ( ! $user_options ) {
			return;
		}

		foreach ( $user_options as $user_option ) {
			$meta_key = $wpdb->get_blog_prefix( $blog_id ) . $user_option;
			$users    = get_users(
				array(
					'blog_id'  => $blog_id,
					'meta_key' => $meta_key,
					'fields'   => array( 'ID' ),
				)
			);

			foreach ( $users as $user ) {
				delete_user_option( $user->ID, $user_option );
			}
		}

		delete_metadata( 'user', 0, \WPML_Notices::USER_DISMISSED_KEY, '', true );
	}

	private function reset_user_capabilities( $blog_id ) {
		$capabilities = apply_filters( 'wpml_reset_user_capabilities', self::RESET_CAPABILITIES, $blog_id );
		if ( ! $capabilities ) {
			return;
		}

		$users = get_users( array( 'blog_id' => $blog_id ) );

		foreach ( $users as $user ) {
			foreach ( $capabilities as $capability ) {
				$user->remove_cap( $capability );
			}
		}

		$this->reset_role_capabilities( $capabilities );
	}

	private function reset_role_capabilities( $capabilities ) {
		$wp_roles = wp_roles();
		if ( ! $wp_roles ) {
			return;
		}

		foreach ( array_keys( $wp_roles->roles ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}

			foreach ( $capabilities as $capability ) {
				if ( $role->has_cap( $capability ) ) {
					$role->remove_cap( $capability );
				}
			}
		}
	}

	private function detach_deactivation_recommendation_listener() {
		if ( ! class_exists( '\WP_Installer' ) || ! method_exists( '\WP_Installer', 'instance' )
			|| ! class_exists( '\OTGS\Installer\Recommendations\RecommendationsManager' )
		) {
			return;
		}

		$installer = \WP_Installer::instance();
		if ( ! $installer instanceof \WP_Installer ) {
			return;
		}

		try {
			$property = new \ReflectionProperty( $installer, 'recommendations_manager' );
			$property->setAccessible( true );
			$manager = $property->getValue( $installer );
		} catch ( \ReflectionException $e ) {
			return;
		}

		if ( $manager instanceof \OTGS\Installer\Recommendations\RecommendationsManager ) {
			remove_action( 'deactivated_plugin', array( $manager, 'deactivatedPluginRecommendation' ) );
		}
	}

	private function deactivate_or_mark_inactive() {
		$wpmu_sitewide_plugins = (array) get_site_option( 'active_sitewide_plugins' );

		if ( ! isset( $wpmu_sitewide_plugins[ WPML_PLUGIN_BASENAME ] ) ) {
			remove_action( 'deactivate_' . WPML_PLUGIN_BASENAME, 'icl_sitepress_deactivate' );
			deactivate_plugins( WPML_PLUGIN_BASENAME );

			$recently                         = (array) get_option( 'recently_activated' );
			$recently[ WPML_PLUGIN_BASENAME ] = time();
			update_option( 'recently_activated', $recently );

			return;
		}

		update_option( '_wpml_inactive', true );
	}

	private function delete_post_deactivation_options( $blog_id ) {
		$options = apply_filters(
			'wpml_reset_options_after_deactivation',
			self::POST_DEACTIVATION_OPTIONS,
			$blog_id
		);

		foreach ( $options as $option ) {
			delete_option( $option );
		}
	}
}
