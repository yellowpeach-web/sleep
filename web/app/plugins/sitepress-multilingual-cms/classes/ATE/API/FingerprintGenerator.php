<?php

namespace WPML\TM\ATE\API;

use WPML\TM\ATE\ClonedSites\SecondaryDomains;

class FingerprintGenerator {
	const SITE_FINGERPRINT_HEADER     = 'SITE-FINGERPRINT';
	const NEW_SITE_FINGERPRINT_HEADER = 'NEW-SITE-FINGERPRINT';

	private $secondaryDomains;

	public function __construct( SecondaryDomains $secondaryDomains ) {
		$this->secondaryDomains = $secondaryDomains;
	}


	public function getSiteFingerprint() {
		$siteFingerprint = [
			'wp_url' => $this->getClonedSiteUrl(),
		];

		return json_encode( $siteFingerprint );
	}

	public function getClonedSiteUrl() {

		$siteUrl = $this->honoursNetworkWideOverrides() && defined( 'ATE_CLONED_SITE_URL' )
			? ATE_CLONED_SITE_URL
			: $this->secondaryDomains->maybeFallBackToTheOriginalURL( $this->ownSiteUrl() );

		return $this->getDefaultSiteUrl( $siteUrl );
	}

	private function getDefaultSiteUrl( $siteUrl ) {
		global $sitepress;
		$filteredSiteUrl = false;
		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN === (int) $sitepress->get_setting( 'language_negotiation_type' ) ) {
			global $wpml_url_converter;
			$site_url_default_lang = $wpml_url_converter->get_default_site_url();
			$filteredSiteUrl       = filter_var( $site_url_default_lang, FILTER_SANITIZE_URL );

			if ( ! $this->isServedByThisSite( $filteredSiteUrl ) ) {
				$filteredSiteUrl = false;
			}
		}

		$defaultSiteUrl = $filteredSiteUrl ? $filteredSiteUrl : $siteUrl;

		if ( $this->honoursNetworkWideOverrides() && defined( 'ATE_CLONED_DEFAULT_SITE_URL' ) ) {
			$defaultSiteUrl = ATE_CLONED_DEFAULT_SITE_URL;
		}

		return $defaultSiteUrl;
	}

	private function ownSiteUrl() {
		if ( ! $this->isNetworkSubsite() ) {
			return site_url();
		}

		return get_site_url( get_current_blog_id() );
	}

	private function honoursNetworkWideOverrides() {
		return ! $this->isNetworkSubsite();
	}

	private function isNetworkSubsite() {
		return function_exists( 'is_multisite' )
			&& is_multisite()
			&& function_exists( 'is_main_site' )
			&& ! is_main_site();
	}

	private function isServedByThisSite( $url ) {
		if ( ! $url ) {
			return false;
		}

		if ( ! $this->isNetworkSubsite() ) {
			return true;
		}

		global $sitepress;

		$host    = $this->hostOf( $url );
		$domains = $sitepress->get_setting( 'language_domains' );
		$domains = is_array( $domains ) ? $domains : [];

		$hosts = array_map( [ $this, 'hostOf' ], $domains );

		$hosts[] = $this->hostOf( $this->ownSiteUrl() );

		return '' !== $host && in_array( $host, $hosts, true );
	}

	private function hostOf( $url ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return '';
		}

		if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $url ) ) {
			$url = '//' . ltrim( $url, '/' );
		}

		$parsed = wp_parse_url( $url );

		return is_array( $parsed ) && isset( $parsed['host'] ) ? (string) $parsed['host'] : '';
	}
}
