<?php

namespace WPML\Compatibility\LiteSpeedCache;

class StaticUrls implements \IWPML_Frontend_Action {

	const PRIORITY = 100;

	public function add_hooks() {
		if ( $this->shouldHook() ) {
			add_filter( 'litespeed_buffer_after', [ $this, 'makeStaticUrlsRelative' ], self::PRIORITY );
		}
	}

	public function shouldHook() {
		global $sitepress;

		return defined( 'LSCWP_V' )
			&& $sitepress instanceof \SitePress
			&& WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN === (int) $sitepress->get_setting( 'language_negotiation_type' );
	}

	public function makeStaticUrlsRelative( $buffer ) {
		if ( ! defined( 'LITESPEED_STATIC_URL' ) ) {
			return $buffer;
		}

		return $this->relativeTo( $buffer, (string) constant( 'LITESPEED_STATIC_URL' ) );
	}

	public function relativeTo( $buffer, $staticUrl ) {
		if ( ! is_string( $buffer ) || '' === $buffer ) {
			return $buffer;
		}

		$parts = self::authorityAndPath( $staticUrl );
		if ( ! $parts ) {
			return $buffer;
		}

		list( $authority, $path ) = $parts;

		if ( ! in_array( \WPML_Language_Domains::normalize( $authority ), $this->ownHosts(), true ) ) {
			return $buffer;
		}

		$prefix  = '//' . $authority . $path . '/';
		$escaped = str_replace( '/', '\/', $prefix );
		$search  = [
			'https:' . $prefix,
			'http:' . $prefix,
			$prefix,
			'https:' . $escaped,
			'http:' . $escaped,
			$escaped,
		];
		$relative = $path . '/';
		$replace  = [
			$relative,
			$relative,
			$relative,
			str_replace( '/', '\/', $relative ),
			str_replace( '/', '\/', $relative ),
			str_replace( '/', '\/', $relative ),
		];

		return str_replace( $search, $replace, $buffer );
	}

	private static function authorityAndPath( $url ) {
		$rest = (string) preg_replace( '#^(?:https?:)?//#i', '', trim( $url ), 1, $count );
		if ( 1 !== $count ) {
			return null;
		}

		$slash     = strpos( $rest, '/' );
		$authority = false === $slash ? $rest : substr( $rest, 0, $slash );
		$path      = false === $slash ? '' : rtrim( substr( $rest, $slash ), '/' );

		if ( '' === $authority || '' === $path ) {
			return null;
		}

		return [ $authority, $path ];
	}

	private function ownHosts() {
		global $sitepress;

		$domains = $sitepress instanceof \SitePress ? $sitepress->get_setting( 'language_domains' ) : [];
		$domains = is_array( $domains ) ? array_values( $domains ) : [];

		$domains[] = \WPML_Default_Site_Url::getHome();

		$hosts = [];
		foreach ( $domains as $domain ) {
			$host = \WPML_Language_Domains::normalize( $domain );
			if ( '' !== $host && ! \WPML_Language_Domains::is_normalize_error( $host ) ) {
				$hosts[] = $host;
			}
		}

		return $hosts;
	}
}
