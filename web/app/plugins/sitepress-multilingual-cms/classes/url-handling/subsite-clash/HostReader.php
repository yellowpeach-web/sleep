<?php

namespace WPML\UrlHandling\SubsiteClash;

use WPML\Core\Component\UrlHandling\Domain\SubsiteClash\ActiveLanguage;
use WPML\Core\Component\UrlHandling\Domain\SubsiteClash\LanguageHost;
use WPML\Core\Component\UrlHandling\Domain\SubsiteClash\SiteLanguageHosts;
use WPML\Core\Component\UrlHandling\Domain\SubsiteClash\SitePath;
use WPML\Core\LanguageNegotiation;
use WPML\Language\WpmlPresence;

class HostReader {

	const QUERY_MARKER = 'wpml-host-clash';

	public function state() {
		global $sitepress;
		global $wpml_language_resolution;

		if ( ! is_multisite() || ! $this->hasSunrise() ) {
			return SiteLanguageHosts::outOfScope();
		}

		$active = ! get_option( MainSiteReader::INACTIVE_OPTION )
			&& $this->wpmlTablesOnCurrentBlog()
			&& $sitepress instanceof \SitePress
			&& $wpml_language_resolution instanceof \WPML_Language_Resolution;

		if ( ! $active || ! wpml_is_setup_complete() ) {
			return SiteLanguageHosts::outOfScope();
		}

		$settings = $sitepress->get_settings();
		$format   = \WPML_URL_Format::of( $settings );
		$default  = (string) $sitepress->get_default_language();

		return new SiteLanguageHosts(
			true,
			true,
			true,
			true,
			$format,
			$default,
			SiteLanguageHosts::FORMAT_DOMAINS === $format ? $this->languageHosts( $sitepress, $wpml_language_resolution, $settings, $default ) : [],
			$this->siteId(),
			$this->sitePath()
		);
	}

	public function rowsAt( array $domains ) {
		global $wpdb;

		if ( ! $domains ) {
			return [];
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT /* wpml-host-clash */ blog_id, site_id, domain, path, archived, deleted, spam, mature FROM {$wpdb->blogs} WHERE domain IN (" . implode( ', ', array_fill( 0, count( $domains ), '%s' ) ) . ')',
				array_values( $domains )
			),
			ARRAY_A
		);

		return MainSiteReader::sitesFromRows( $rows );
	}

	public function siteId(): int {
		return (int) get_current_blog_id();
	}

	public function sitePath(): string {
		$site = get_site();

		return $site ? (string) $site->path : '/';
	}

	public function networkDomain(): string {
		$network = get_network();

		return $network ? (string) $network->domain : '';
	}

	protected function hasSunrise(): bool {
		return (bool) LanguageNegotiation::isDomainModeAvailable();
	}

	protected function wpmlTablesOnCurrentBlog(): bool {
		return WpmlPresence::onCurrentBlog();
	}

	private function languageHosts( \SitePress $sitepress, \WPML_Language_Resolution $resolution, array $settings, $default ) {
		$domains = isset( $settings['language_domains'] ) && is_array( $settings['language_domains'] ) ? $settings['language_domains'] : [];
		$admin   = $sitepress->get_admin_language();

		$hosts = [];
		foreach ( array_map( 'strval', (array) $resolution->get_active_language_codes() ) as $code ) {
			$host = $code !== $default && isset( $domains[ $code ] ) && is_scalar( $domains[ $code ] )
				? \WPML_Language_Domains::lookupHostOf( (string) $domains[ $code ] )
				: null;

			if ( null !== $host ) {
				$hosts[] = new LanguageHost(
					new ActiveLanguage( $code, $code, (string) $sitepress->get_display_language_name( $code, $admin ), (bool) $resolution->is_language_hidden( $code ) ),
					$host
				);
			}
		}

		return $hosts;
	}
}
