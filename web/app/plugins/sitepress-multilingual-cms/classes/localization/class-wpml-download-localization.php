<?php

use WPML\Localization\TranslationsApiBreaker;

class WPML_Download_Localization {
	private $active_languages;
	private $default_language;
	private $not_founds = array();
	private $errors     = array();

	private $deadline = null;

	private $processed_language_codes = array();

	private $cut_short = false;

	private $skip_plugin_packs = array();

	private $processed_plugin_packs = array();

	public function __construct( array $active_languages, $default_language ) {
		$this->active_languages = $active_languages;
		$this->default_language = $default_language;
	}

	public function download_language_packs() {
		TranslationsApiBreaker::arm();
		try {
			$results = $this->download_core_language_packs_unguarded();

			if ( false === $results ) {
				return array();
			}

			$this->download_active_plugins_language_packs();

			return $results;
		} finally {
			TranslationsApiBreaker::disarm();
		}
	}

	public function download_core_language_packs() {
		TranslationsApiBreaker::arm();
		try {
			return $this->download_core_language_packs_unguarded();
		} finally {
			TranslationsApiBreaker::disarm();
		}
	}

	public function set_deadline( $unix_timestamp ) {
		$this->deadline = (float) $unix_timestamp;
	}

	public function get_processed_language_codes() {
		return $this->processed_language_codes;
	}

	public function set_skipped_plugin_packs( array $keys ) {
		$this->skip_plugin_packs = array_fill_keys( array_map( 'strval', $keys ), true );
	}

	public function get_processed_plugin_packs() {
		return $this->processed_plugin_packs;
	}

	public function was_cut_short() {
		return $this->cut_short;
	}

	protected function now() {
		return microtime( true );
	}

	private function download_core_language_packs_unguarded() {
		$results                        = array();
		$this->processed_language_codes = array();
		$this->cut_short                = false;

		if ( $this->active_languages ) {
			if ( ! function_exists( 'request_filesystem_credentials' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			$translation_install_file = $this->get_translation_install_file();

			if ( ! file_exists( $translation_install_file ) ) {
				return false;
			}
			if ( ! function_exists( 'wp_can_install_language_pack' ) ) {
				require_once $translation_install_file;
			}
			if ( ! function_exists( 'submit_button' ) ) {
				require_once ABSPATH . 'wp-admin/includes/template.php';
			}
			if ( ! wp_can_install_language_pack() ) {
				$this->errors[] = 'wp_can_install_language_pack';
			} else {
				foreach ( $this->active_languages as $code => $active_language ) {
					if ( null !== $this->deadline && $this->now() >= $this->deadline ) {
						$this->cut_short = true;

						break;
					}

					$result = $this->download_language_pack( $active_language );
					if ( $result ) {
						$results[] = $result;
					}

					$this->processed_language_codes[] = isset( $active_language['code'] )
						? (string) $active_language['code']
						: (string) $code;
				}
			}
		}

		return $results;
	}

	protected function get_translation_install_file() {
		return ABSPATH . 'wp-admin/includes/translation-install.php';
	}

	public function get_not_founds() {
		return $this->not_founds;
	}

	public function get_errors() {
		return $this->errors;
	}

	private function download_language_pack( $language ) {
		$result = null;

		if ( 'en_US' !== $language['default_locale'] ) {
			$mapped_locale = $this->get_mapped_locale( $language );
			$candidates    = array(
				$language['default_locale'],
				isset( $language['tag'] ) ? $language['tag'] : '',
				isset( $language['code'] ) ? $language['code'] : '',
			);

			if ( $mapped_locale && ! in_array( $mapped_locale, $candidates, true ) ) {
				$result = wp_download_language_pack( $mapped_locale );
			}

			if ( ! $result && $language['default_locale'] ) {
				$result = wp_download_language_pack( $language['default_locale'] );
			}
			if ( ! $result && $language['tag'] ) {
				$result = wp_download_language_pack( $language['tag'] );
			}
			if ( ! $result && $language['code'] ) {
				$result = wp_download_language_pack( $language['code'] );
			}

			if ( ! $result ) {
				$result             = null;
				$this->not_founds[] = $language;
			}
		}

		return $result;
	}

	public function download_active_plugins_language_packs() {
		if ( ! self::get_active_plugins() ) {
			return;
		}

		$this->download_plugin_translations_bulk();
	}

	public static function get_active_plugins() {
		$active_plugins = get_option( 'active_plugins' );
		$active_plugins = is_array( $active_plugins ) ? $active_plugins : array();

		if ( is_multisite() ) {
			$sitewide_plugins = get_site_option( 'active_sitewide_plugins' );
			if ( is_array( $sitewide_plugins ) ) {
				$active_plugins = array_merge( $active_plugins, array_keys( $sitewide_plugins ) );
			}
		}

		return array_values( array_unique( $active_plugins ) );
	}

	private function get_mapped_locale( array $language ) {
		global $sitepress;

		if ( empty( $language['code'] ) || ! isset( $sitepress ) || ! method_exists( $sitepress, 'get_locale' ) ) {
			return '';
		}

		return (string) $sitepress->get_locale( $language['code'] );
	}

	private function get_accepted_locales() {
		$locales = array_map(
			function ( $language ) {
				return $language['default_locale'];
			},
			$this->active_languages
		);

		foreach ( $this->active_languages as $language ) {
			$mapped_locale = $this->get_mapped_locale( $language );
			if ( $mapped_locale && ! in_array( $mapped_locale, $locales, true ) ) {
				$locales[] = $mapped_locale;
			}
		}

		return $locales;
	}

	public function download_plugin_translations_bulk() {
		TranslationsApiBreaker::arm();
		try {
			return $this->download_plugin_translations_bulk_unguarded();
		} finally {
			TranslationsApiBreaker::disarm();
		}
	}

	private function download_plugin_translations_bulk_unguarded() {
		$this->cut_short              = false;
		$this->processed_plugin_packs = array();

		if ( ! function_exists( 'wp_update_plugins' ) || ! function_exists( 'wp_get_installed_translations' ) ) {
			return false;
		}

		if ( ! class_exists( 'Automatic_Upgrader_Skin' ) ) {
			require_once ABSPATH . '/wp-admin/includes/class-wp-upgrader.php';
		}

		$this->run_plugins_update_check();

		$to_upgrade = array();

		foreach ( $this->collect_offered_plugin_packs() as $pack ) {
			if ( ! isset( $this->skip_plugin_packs[ self::plugin_pack_key( $pack ) ] ) ) {
				$to_upgrade[] = $pack;
			}
		}

		if ( ! $to_upgrade ) {
			return true;
		}

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Language_Pack_Upgrader( $skin );
		$results  = array();

		foreach ( $to_upgrade as $index => $pack ) {
			if ( $index > 0 && null !== $this->deadline && $this->now() >= $this->deadline ) {
				$this->cut_short = true;

				break;
			}

			$result = $upgrader->bulk_upgrade( array( $pack ), array( 'clear_update_cache' => false ) );

			$this->processed_plugin_packs[] = self::plugin_pack_key( $pack );

			$results = array_merge( $results, is_array( $result ) ? $result : array( $result ) );
		}

		wp_clean_update_cache();

		return $results;
	}

	private static function plugin_pack_key( $pack ) {
		return $pack->slug . '|' . $pack->language;
	}

	private function run_plugins_update_check() {
		$locales = array_values( array_unique( $this->get_accepted_locales() ) );

		$add_locales = function ( $requested ) use ( $locales ) {
			return array_values( array_unique( array_merge( array_values( (array) $requested ), $locales ) ) );
		};

		add_filter( 'plugins_update_check_locales', $add_locales );

		try {
			$this->expire_plugins_update_check();
			wp_update_plugins();
		} finally {
			remove_filter( 'plugins_update_check_locales', $add_locales );
		}
	}

	private function expire_plugins_update_check() {
		$current = get_site_transient( 'update_plugins' );

		if ( ! is_object( $current ) ) {
			return;
		}

		$current->last_checked = 0;

		set_site_transient( 'update_plugins', $current );
	}

	private function collect_offered_plugin_packs() {
		$current = get_site_transient( 'update_plugins' );
		$offered = is_object( $current ) && ! empty( $current->translations ) ? (array) $current->translations : array();

		if ( ! $offered ) {
			return array();
		}

		$locales   = $this->get_accepted_locales();
		$installed = wp_get_installed_translations( 'plugins' );
		$installed = is_array( $installed ) ? $installed : array();

		$to_upgrade = array();

		foreach ( $offered as $offer ) {
			$offer = (array) $offer;

			if (
				! isset( $offer['type'], $offer['slug'], $offer['language'], $offer['package'] ) ||
				'plugin' !== $offer['type'] ||
				! in_array( $offer['language'], $locales, true )
			) {
				continue;
			}

			$slug           = (string) $offer['slug'];
			$installed_pack = isset( $installed[ $slug ] ) && is_array( $installed[ $slug ] ) ? $installed[ $slug ] : array();

			if ( $this->is_translation_up_to_date( $offer, $installed_pack ) ) {
				continue;
			}

			$to_upgrade[] = (object) array(
				'language' => $offer['language'],
				'type'     => 'plugin',
				'slug'     => $slug,
				'version'  => isset( $offer['version'] ) ? $offer['version'] : '',
				'package'  => $offer['package'],
			);
		}

		return $to_upgrade;
	}

	public function download_plugin_translations( string $plugin ) {
		TranslationsApiBreaker::arm();
		try {
			return $this->download_plugin_translations_unguarded( $plugin );
		} finally {
			TranslationsApiBreaker::disarm();
		}
	}

	private function download_plugin_translations_unguarded( $plugin ) {
		$plugin_file = WP_PLUGIN_DIR . '/' . $plugin;
		if ( ! file_exists( $plugin_file ) ) {
			return false;
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		if ( ! function_exists( 'translations_api' ) ) {
			require_once ABSPATH . '/wp-admin/includes/translation-install.php';
		}

		if ( ! class_exists( 'Automatic_Upgrader_Skin' ) ) {
			require_once ABSPATH . '/wp-admin/includes/class-wp-upgrader.php';
		}

		$plugin_slug    = dirname( $plugin );
		$plugin_data    = get_plugin_data( $plugin_file, false, false );
		$plugin_version = $plugin_data['Version'];

		$api = translations_api(
			'plugins',
			[
				'slug'    => $plugin_slug,
				'version' => $plugin_version,
			]
		);

		if ( is_wp_error( $api ) ) {
			return $api;
		}

		if ( empty( $api['translations'] ) ) {
			return true;
		}

		$toUpgrade = array();

		$locales = $this->get_accepted_locales();

		$installed = wp_get_installed_translations( 'plugins' );
		$installed = isset( $installed[ $plugin_slug ] ) ? $installed[ $plugin_slug ] : array();

		foreach ( $api['translations'] as $translation ) {
			if (
				! array_key_exists( 'language', $translation ) ||
				! array_key_exists( 'package', $translation ) ||
				! in_array( $translation['language'], $locales, true )
			) {
				continue;
			}

			if ( $this->is_translation_up_to_date( $translation, $installed ) ) {
				continue;
			}

			$toUpgrade[] = (object) [
				'language' => $translation['language'],
				'type'     => 'plugin',
				'slug'     => $plugin_slug,
				'version'  => $plugin_version,
				'package'  => $translation['package'],
			];
		}

		if ( empty( $toUpgrade ) ) {
			return true;
		}

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Language_Pack_Upgrader( $skin );
		return $upgrader->bulk_upgrade( $toUpgrade );
	}

	private function is_translation_up_to_date( array $translation, array $installed ) {
		if ( ! isset( $installed[ $translation['language'] ]['PO-Revision-Date'] ) ) {
			return false;
		}

		if ( empty( $translation['updated'] ) ) {
			return true;
		}

		$local  = strtotime( $installed[ $translation['language'] ]['PO-Revision-Date'] );
		$remote = strtotime( $translation['updated'] );

		return $local >= $remote;
	}
}
