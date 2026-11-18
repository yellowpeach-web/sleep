<?php

namespace WPML\Troubleshooting;

class BootOptions {

	const ALWAYS = [
		'_icl_admin_option_names', '_wpml_media', 'wpml_language_packs_waiting_for_st',
		'wpml_login_page_translation', 'wpml_login_page_translation_update_rules', 'wpml_pb_has_shortcode_settings',
		'wpml_single_url_resolution_generation', 'wpml_appearance_write_failures', 'wpml_ate_handshake_state',
	];

	const ALWAYS_BY_PLUGIN = [
		'wpml-string-translation/' => [
			'_icl_admin_option_id_names', 'wpml_st_plural_forms',
			'_transient_wpml_st_upgrade_in_progress', '_transient_timeout_wpml_st_upgrade_in_progress',
			'wpml_tax_slug_translation_settings',
		],
		'wpml-cms-nav/'            => [ '_wpml_cms_nav_cache' ],
		'wpml-sticky-links/'       => [ 'alp_settings' ],
		'woocommerce-multilingual/' => [ '_wcml_4_1_0_migration_required' ],
	];

	const ADMIN = [
		'wpml_resolved_url_persist', '_icl_cache_language_details', 'wpml-xml-config-update-log',
		'_transient_wpml_core_update_in_progress', '_transient_timeout_wpml_core_update_in_progress',
		'auto-translation-job-creation-error', 'wpml-tm-translation-jobs-migration-skipped', 'wpml_posthog_tracking_mode',
		'_transient_wpml_tm_stored_sitekey', '_transient_timeout_wpml_tm_stored_sitekey',
		'wpml-package-translation-refresh-required', 'wpml_notices', '_wpml_dismissed_notices', 'otgs_share_local_components',
		'wpml_posthog_optout_pending_stop_reason', 'wpml_words_to_translate_failures', 'wpml_ate_auto_migration_data',
		'wpml_ate_reconnect_state', 'wpml_posthog_enabled', 'wpml_fixing_migration_log',
		'wpml_tm_migration_invalid_jobs_already_synced',
	];

	const ADMIN_BY_PLUGIN = [
		'wpml-string-translation/' => [ 'wpml_st_pack_translations_reload_pending' ],
		'wpml-media-translation/'  => [ 'wpml_media_translation_dashboard_items_per_page' ],
	];

	const ADMIN_PAGE = [
		'wpml_ate_manage_credits_site', 'wpml_ate_retranslation_state', 'wpml_comment_counts',
		'wpml_meta_settings_incidents', 'wpml_requirements', 'wpml_root_page_deleted',
		'wpml_tm_ate_pending_cancels', 'wpml_translations_table_keys_check', 'WPML(post-type)',
		'wpml_automatic_service_selection_failed', 'wpml_proxy_enabled', 'wpml_posthog_cache_last_checked',
	];

	const ADMIN_PAGE_BY_PLUGIN = [
		'wpml-string-translation/' => [ 'wpml_st_deferred_upgrade' ],
	];

	public static function prime() {
		if ( function_exists( 'wp_prime_option_caches' ) ) {
			wp_prime_option_caches( self::names() );
		}
	}

	public static function names() {
		$admin  = is_admin();
		$page   = $admin && ! wp_doing_ajax();
		$names  = $admin ? array_merge( self::ALWAYS, self::ADMIN, $page ? self::ADMIN_PAGE : [] ) : self::ALWAYS;
		$active = (array) get_option( 'active_plugins', [] );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) );
		}

		$lists = $admin ? [ self::ALWAYS_BY_PLUGIN, self::ADMIN_BY_PLUGIN, $page ? self::ADMIN_PAGE_BY_PLUGIN : [] ] : [ self::ALWAYS_BY_PLUGIN ];
		foreach ( $lists as $byPlugin ) {
			foreach ( $byPlugin as $folder => $folderNames ) {
				foreach ( $active as $plugin ) {
					if ( is_string( $plugin ) && 0 === strpos( $plugin, $folder ) ) {
						$names = array_merge( $names, $folderNames );
						break;
					}
				}
			}
		}

		return array_values( array_unique( $names ) );
	}
}
