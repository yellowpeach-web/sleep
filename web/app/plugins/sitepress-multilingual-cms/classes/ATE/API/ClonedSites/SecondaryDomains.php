<?php

namespace WPML\TM\ATE\ClonedSites;

use WPML\LIB\WP\Option;
use WPML\TM\ATE\API\FingerprintGenerator;

class SecondaryDomains {
	const OPTION = 'wpml_tm_ate_secondary_domains';
	const ORIGINAL_SITE_URL = 'wpml_tm_ate_original_site_url';

	public function add( $domain, $originalSiteUrl ) {
		$domains = $this->get();
		if ( ! in_array( $domain, $domains, true ) ) {
			$domains[] = $domain;
		}

		Option::update( self::OPTION, $domains );
		Option::update( self::ORIGINAL_SITE_URL, $originalSiteUrl );

		return $domains;
	}

	public function maybeFallBackToTheOriginalURL( $currentSiteUrl ) {
		$originalSiteUrl = Option::get( self::ORIGINAL_SITE_URL );

		if ( $currentSiteUrl === $originalSiteUrl ) {
			return $currentSiteUrl;
		}

		if ( $this->isRegistered( $currentSiteUrl ) && ! $this->isAnotherSiteOfTheNetwork( $originalSiteUrl ) ) {
			return $originalSiteUrl;
		}

		return $currentSiteUrl;
	}

	public function isAnotherSiteOfTheNetwork( $url ) {
		if ( ! function_exists( 'is_multisite' ) || ! is_multisite() ) {
			return false;
		}

		$address = $this->addressOf( $url );
		if ( null === $address ) {
			return false;
		}

		$site = get_site_by_path( $address['domain'], $address['path'] );
		if ( ! is_object( $site ) || ! isset( $site->blog_id ) ) {
			return false;
		}

		$blogId = (int) $site->blog_id;
		if ( $blogId === (int) get_current_blog_id() ) {
			return false;
		}

		$sitePath = isset( $site->path ) ? (string) $site->path : '/';
		if ( '/' !== $sitePath || '/' === $address['path'] ) {
			return true;
		}

		foreach ( [ get_home_url( $blogId ), get_site_url( $blogId ) ] as $ownUrl ) {
			$own = $this->addressOf( $ownUrl );
			if ( null !== $own && $own['domain'] === $address['domain'] && $own['path'] === $address['path'] ) {
				return true;
			}
		}

		return false;
	}

	private function addressOf( $url ) {
		$parsed = wp_parse_url( trim( (string) $url ) );
		if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
			return null;
		}

		$path = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';

		return [
			'domain' => strtolower( (string) $parsed['host'] ) . ( isset( $parsed['port'] ) ? ':' . $parsed['port'] : '' ),
			'path'   => '' === $path ? '/' : '/' . $path . '/',
		];
	}

	public function getInfo() {
		$domains = $this->get();

		$domains = $this->checkIfTheOriginalSiteUrlIsInAliasDomains( $domains );

		if ( ! $domains ) {
			return null;
		}

		return [
			'originalSiteUrl' => Option::get( self::ORIGINAL_SITE_URL ),
			'aliasDomains'    => $domains,
		];
	}

	public function reset() {
		Option::delete( self::OPTION );
		Option::delete( self::ORIGINAL_SITE_URL );
	}

	private function get() {
		return Option::getOr( self::OPTION, [] );
	}

	private function isRegistered( $domain ) {
		return in_array( $domain, $this->get(), true );
	}

	private function checkIfTheOriginalSiteUrlIsInAliasDomains( $domains ) {
		$originalSiteUrl = Option::get( self::ORIGINAL_SITE_URL );
		if ( in_array( $originalSiteUrl, $domains, true ) ) {
			Option::delete( self::OPTION );
			Option::delete( self::ORIGINAL_SITE_URL );

			return null;
		}

		return $domains;
	}
}