<?php

namespace WPML\Troubleshooting\Actions;

use WPML_Cache_Directory;
use WPML_Translation_Roles_Records;
use WPML_WP_API;

class CacheClear {

	const TRANSIENT_OPTION_PREFIXES = array(
		'_transient_wpml_' => true,
		'_transient_wpml-' => true,
		'_transient__icl_' => true,
		'_wpml_transient_' => false,
	);

	const TRANSIENT_OPTION_PREFIX = '_transient_';

	const KNOWN_TRANSIENTS = array(
		'wpml_st_context_collation',
		'wpml_string_translation_has_mo_domains',
		'wpml-tm-ams-api-cache',
		'wpml-ate-jobs-count',
		'wpml_translation_services_list',
		'wpml_ams_engines_catalog',
		'wpml_twig_cache_unwritable',
	);

	const KNOWN_OPTIONS = array(
		'wpml_requirements',
		'wpml_rest_api_status',
		'wpml_requirements_refresh_lock',
		'wpml_pb_block_default_strings',
	);

	const ST_MO_CACHE_ID = 'wpml-st-custom-mo-files';
	const ST_MO_LOCALES  = 'locales';

	public function run() {
		$cleared = false !== icl_cache_clear();

		$this->cache_directory()->remove();

		delete_option( \WPML_Templates_Factory::OTGS_TWIG_CACHE_DISABLED_KEY );

		$this->delete_roles_cache();

		if ( ! $this->delete_option_backed_transients() ) {
			$cleared = false;
		}

		$this->delete_known_transients();

		foreach ( self::KNOWN_OPTIONS as $option_name ) {
			delete_option( $option_name );
		}

		do_action( 'wpml_comment_counts_mark_out_of_date' );

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		return $cleared;
	}

	protected function cache_directory() {
		return new WPML_Cache_Directory( new WPML_WP_API() );
	}

	protected function delete_roles_cache() {
		WPML_Translation_Roles_Records::delete_cache();
	}

	private function delete_option_backed_transients() {
		global $wpdb;

		if ( ! isset( $wpdb, $wpdb->options ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_col' ) ) {
			return true;
		}

		$swept = true;

		foreach ( self::TRANSIENT_OPTION_PREFIXES as $prefix => $is_real_transient ) {
			$escaped = $wpdb->esc_like( $prefix );

			$option_names = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
					$escaped . '%'
				)
			);

			if ( ! empty( $wpdb->last_error ) ) {
				$swept = false;
				continue;
			}

			foreach ( (array) $option_names as $option_name ) {
				$option_name = (string) $option_name;

				if ( 0 !== strpos( $option_name, $prefix ) ) {
					continue;
				}

				if ( $is_real_transient ) {
					delete_transient( substr( $option_name, strlen( self::TRANSIENT_OPTION_PREFIX ) ) );
				} else {
					delete_option( $option_name );
				}
			}
		}

		return $swept;
	}

	private function delete_known_transients() {
		foreach ( self::KNOWN_TRANSIENTS as $transient ) {
			delete_transient( $transient );
		}

		$locales_key = self::ST_MO_CACHE_ID . '-' . self::ST_MO_LOCALES;
		$locales     = get_transient( $locales_key );

		if ( is_array( $locales ) ) {
			foreach ( $locales as $locale ) {
				delete_transient( self::ST_MO_CACHE_ID . '-' . $locale );
			}
		}

		delete_transient( $locales_key );
	}
}
