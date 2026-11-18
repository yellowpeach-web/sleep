<?php

namespace WPML\UrlHandling\SubsiteClash;

use WPML\Core\Component\LanguageEditor\Domain\CustomLanguageFormat;
use WPML\Core\Component\UrlHandling\Domain\SubsiteClash\ActiveLanguage;
use WPML\Core\Component\UrlHandling\Domain\SubsiteClash\MainSiteUrlState;
use WPML\Core\Component\UrlHandling\Domain\SubsiteClash\SitePath;
use WPML\Language\WpmlPresence;
use WPML\LIB\WP\WPDB as WpWPDB;

class MainSiteReader {

	const QUERY_MARKER = 'wpml-subsite-clash';

	const SETTINGS_OPTION = 'icl_sitepress_settings';
	const INACTIVE_OPTION = '_wpml_inactive';

	public function state() {
		if ( ! is_multisite() ) {
			return MainSiteUrlState::withoutSubdirectoryNetwork( false, false );
		}

		if ( is_subdomain_install() ) {
			return MainSiteUrlState::withoutSubdirectoryNetwork( true, true );
		}

		$mainSiteId = (int) get_main_site_id();
		$network = get_network();
		$domain  = $network ? (string) $network->domain : '';
		$path    = $network ? (string) $network->path : '/';

		return (int) get_current_blog_id() === $mainSiteId
			? $this->fromCurrentBlog( $mainSiteId, $domain, $path )
			: $this->fromMainSiteRows( $mainSiteId, $domain, $path );
	}

	public function sitesAt( array $paths, $domain ) {
		global $wpdb;

		if ( ! $paths ) {
			return [];
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT /* wpml-subsite-clash */ blog_id, site_id, domain, path, archived, deleted, spam, mature FROM {$wpdb->blogs} WHERE domain = %s AND path IN (" . implode( ', ', array_fill( 0, count( $paths ), '%s' ) ) . ')',
				array_merge( [ (string) $domain ], array_values( $paths ) )
			),
			ARRAY_A
		);

		return self::sitesFromRows( $rows );
	}

	public static function sitesFromRows( $rows ) {
		$sites = [];
		foreach ( is_array( $rows ) ? $rows : [] as $row ) {
			$sites[] = new SitePath(
				(int) $row['blog_id'],
				(int) $row['site_id'],
				(string) $row['domain'],
				(string) $row['path'],
				set_url_scheme( 'http://' . $row['domain'] . $row['path'] ),
				[
					SitePath::STATUS_ARCHIVED    => ! empty( $row['archived'] ),
					SitePath::STATUS_DEACTIVATED => ! empty( $row['deleted'] ),
					SitePath::STATUS_SPAM        => ! empty( $row['spam'] ),
					SitePath::STATUS_MATURE      => ! empty( $row['mature'] ),
				]
			);
		}

		return $sites;
	}

	public function siteNames( array $ids ) {
		global $wpdb;

		$names = [];
		if ( ! $ids ) {
			return $names;
		}

		$previousBlogId = null;
		try {
			foreach ( $ids as $id ) {
				$blogId = $wpdb->set_blog_id( (int) $id );
				if ( null === $previousBlogId ) {
					$previousBlogId = $blogId;
				}
				$names[ (int) $id ] = (string) WpWPDB::withoutError(
					function () use ( $wpdb ) {
						return $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'blogname' ) );
					}
				);
			}
		} finally {
			if ( null !== $previousBlogId ) {
				$wpdb->set_blog_id( $previousBlogId );
			}
		}

		return $names;
	}

	private function fromCurrentBlog( $mainSiteId, $domain, $path ) {
		global $sitepress;
		global $wpml_language_resolution;

		$active = ! get_option( self::INACTIVE_OPTION )
			&& $this->wpmlTablesOnCurrentBlog()
			&& $sitepress instanceof \SitePress
			&& $wpml_language_resolution instanceof \WPML_Language_Resolution;

		if ( ! $active ) {
			return $this->inactive( $mainSiteId, $domain, $path );
		}

		$settings = $sitepress->get_settings();
		$urls     = isset( $settings['urls'] ) && is_array( $settings['urls'] ) ? $settings['urls'] : [];

		$codes = array_values( array_filter( array_map( 'strval', (array) $wpml_language_resolution->get_active_language_codes() ) ) );
		$urlCodes = $codes ? apply_filters( 'wpml_language_codes_map', array_combine( $codes, $codes ) ) : [];
		$urlCodes = is_array( $urlCodes ) ? $urlCodes : [];

		$adminLanguage = $sitepress->get_admin_language();
		$languages     = [];
		foreach ( $codes as $code ) {
			$languages[] = new ActiveLanguage(
				$code,
				isset( $urlCodes[ $code ] ) && is_scalar( $urlCodes[ $code ] ) ? (string) $urlCodes[ $code ] : $code,
				(string) $sitepress->get_display_language_name( $code, $adminLanguage ),
				(bool) $wpml_language_resolution->is_language_hidden( $code )
			);
		}

		return new MainSiteUrlState(
			true,
			false,
			true,
			(bool) wpml_is_setup_complete(),
			\WPML_URL_Format::of( $settings ),
			! empty( $urls['directory_for_default_language'] ),
			(string) $sitepress->get_default_language(),
			$languages,
			$mainSiteId,
			$domain,
			$path
		);
	}

	private function fromMainSiteRows( $mainSiteId, $domain, $path ) {
		global $wpdb;

		$previousBlogId = $wpdb->set_blog_id( $mainSiteId );
		try {
			$options = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN (%s, %s, %s)",
					self::SETTINGS_OPTION,
					self::INACTIVE_OPTION,
					'active_plugins'
				),
				OBJECT_K
			);
			$rows    = WpWPDB::withoutError(
				function () use ( $wpdb ) {
					return $wpdb->get_results( "SELECT code, display_code FROM {$wpdb->prefix}icl_languages WHERE active = 1", ARRAY_A );
				}
			);
		} finally {
			$wpdb->set_blog_id( $previousBlogId );
		}

		$options  = is_array( $options ) ? $options : [];
		$settings = self::unserializeRaw( isset( $options[ self::SETTINGS_OPTION ] ) ? $options[ self::SETTINGS_OPTION ]->option_value : null );
		$plugins  = self::unserializeRaw( isset( $options['active_plugins'] ) ? $options['active_plugins']->option_value : null );
		$inactive = isset( $options[ self::INACTIVE_OPTION ] ) && ! empty( $options[ self::INACTIVE_OPTION ]->option_value );

		if ( $inactive || ! self::runsWpml( $plugins ) || ! is_array( $rows ) || ! $rows ) {
			return $this->inactive( $mainSiteId, $domain, $path );
		}

		$hidden    = isset( $settings['hidden_languages'] ) && is_array( $settings['hidden_languages'] ) ? array_map( 'strval', $settings['hidden_languages'] ) : [];
		$languages = [];
		foreach ( $rows as $row ) {
			$code        = (string) $row['code'];
			$urlCode     = CustomLanguageFormat::usableDisplayCode( isset( $row['display_code'] ) ? $row['display_code'] : null );
			$languages[] = new ActiveLanguage( $code, '' === $urlCode ? $code : $urlCode, '', in_array( $code, $hidden, true ) );
		}

		$urls = isset( $settings['urls'] ) && is_array( $settings['urls'] ) ? $settings['urls'] : [];

		return new MainSiteUrlState(
			true,
			false,
			true,
			! empty( $settings['setup_complete'] ),
			\WPML_URL_Format::of( $settings ),
			! empty( $urls['directory_for_default_language'] ),
			isset( $settings['default_language'] ) ? (string) $settings['default_language'] : '',
			$languages,
			$mainSiteId,
			$domain,
			$path
		);
	}

	protected function wpmlTablesOnCurrentBlog(): bool {
		return WpmlPresence::onCurrentBlog();
	}

	private function inactive( $mainSiteId, $domain, $path ) {
		return new MainSiteUrlState( true, false, false, false, MainSiteUrlState::FORMAT_DIRECTORIES, false, '', [], $mainSiteId, $domain, $path );
	}

	private static function runsWpml( $mainSitePlugins ): bool {
		$basename = defined( 'WPML_PLUGIN_BASENAME' ) ? WPML_PLUGIN_BASENAME : 'sitepress-multilingual-cms/sitepress.php';

		if ( is_array( $mainSitePlugins ) && in_array( $basename, $mainSitePlugins, true ) ) {
			return true;
		}

		$networkPlugins = get_site_option( 'active_sitewide_plugins' );

		return is_array( $networkPlugins ) && isset( $networkPlugins[ $basename ] );
	}

	private static function unserializeRaw( $value ) {
		if ( ! is_string( $value ) || ! is_serialized( $value ) ) {
			return $value;
		}

		return @unserialize( trim( $value ), [ 'allowed_classes' => false ] );
	}
}
