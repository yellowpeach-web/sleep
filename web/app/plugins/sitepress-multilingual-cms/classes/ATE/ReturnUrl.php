<?php

namespace WPML\TM\ATE;

use WPML\FP\Obj;
use WPML\UIPage;

class ReturnUrl {

	public static function sanitize( $returnUrl ) {
		$fallback = \admin_url( UIPage::getTMDashboard() );

		$returnUrl = \filter_var( (string) $returnUrl, FILTER_SANITIZE_URL );

		if ( ! $returnUrl ) {
			return $fallback;
		}

		$parts = \wp_parse_url( $returnUrl );

		if ( ! is_array( $parts ) ) {
			return $fallback;
		}

		$scheme = strtolower( (string) Obj::propOr( '', 'scheme', $parts ) );

		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			return $fallback;
		}

		return self::isThisSiteAdmin( $parts ) ? $returnUrl : $fallback;
	}

	private static function isThisSiteAdmin( array $parts ) {
		$adminParts = \wp_parse_url( \get_admin_url() );

		if ( ! is_array( $adminParts ) ) {
			return false;
		}

		$authority = self::getAuthority( $parts );

		return '' !== $authority && $authority === self::getAuthority( $adminParts );
	}

	private static function getAuthority( array $parts ) {
		$host = strtolower( (string) Obj::propOr( '', 'host', $parts ) );
		$port = Obj::prop( 'port', $parts );

		return $port ? $host . ':' . $port : $host;
	}
}
