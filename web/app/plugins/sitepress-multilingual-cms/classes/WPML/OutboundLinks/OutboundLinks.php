<?php

namespace WPML\OutboundLinks;

use WPML\Core\SharedKernel\Component\WpmlOrgClient\Domain\WpmlOrgOrigin;

/**
 * Central builder for every user-facing outbound link that points at the
 * wpml.org family of sites (wpml.org and any *.wpml.org subdomain).
 *
 * Every link the plugin shows to a customer and that goes to wpml.org MUST be
 * routed through {@see OutboundLinks::to()} instead of being written as a raw
 * literal. The helper appends a single, normalized set of tracking parameters
 * so that plugin-referred journeys can be attributed on the destination side:
 *
 *   utm_source  = wpml-plugin   (one fixed spelling)
 *   utm_medium  = <surface>      (wizard, dashboard, notice, ...)
 *   utm_campaign= <flow>         (stable feature/flow name)
 *   utm_content = <slot>         (optional, when a screen links the same target twice)
 *   wpv         = <plugin version>
 *
 * Privacy rule (hard): nothing identifying the customer's site is ever added to
 * a link. Surface + flow + version only. No site URL, no admin e-mail, no
 * license key.
 *
 * The full convention, vocabulary and campaign list live in
 * docs/OUTBOUND-LINKS.md.
 *
 * @since 4.9.0
 */
class OutboundLinks {

	const SOURCE = 'wpml-plugin';

	const OWNED_PARAM_PREFIXES = [ 'utm_' ];
	const OWNED_PARAMS         = [ 'wpv' ];

	public static function to( $url, array $args = [] ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return $url;
		}

		$host = self::extractHost( $url );

		if ( ! self::isWpmlHost( $host ) ) {
			return $url;
		}

		list( $base, $query, $fragment ) = self::splitUrl( $url );

		$preserved = self::stripOwnedParams( $query );

		$params = self::buildParams( $args );

		$newQuery = self::mergeQuery( $preserved, $params );

		$result = $base;

		if ( '' !== $newQuery ) {
			$result .= '?' . $newQuery;
		}

		if ( '' !== $fragment ) {
			$result .= '#' . $fragment;
		}

		return WpmlOrgOrigin::map( $result );
	}

	public static function getJsConfig() {
		$config = [
			'source'  => self::SOURCE,
			'version' => self::version(),
		];

		if ( ! WpmlOrgOrigin::isProduction() ) {
			$config['estate'] = WpmlOrgOrigin::fronts();
		}

		return $config;
	}

	private static function buildParams( array $args ) {
		$segments = [];

		$segments[] = 'utm_source=' . self::SOURCE;

		$medium = isset( $args['medium'] ) ? self::slug( $args['medium'] ) : '';
		if ( '' !== $medium ) {
			$segments[] = 'utm_medium=' . $medium;
		}

		$campaign = isset( $args['campaign'] ) ? self::slug( $args['campaign'] ) : '';
		if ( '' !== $campaign ) {
			$segments[] = 'utm_campaign=' . $campaign;
		}

		$content = isset( $args['content'] ) ? self::slug( $args['content'] ) : '';
		if ( '' !== $content ) {
			$segments[] = 'utm_content=' . $content;
		}

		$version = self::version();
		if ( '' !== $version ) {
			$segments[] = 'wpv=' . rawurlencode( $version );
		}

		return $segments;
	}

	private static function mergeQuery( array $preserved, array $params ) {
		return implode( '&', array_merge( $preserved, $params ) );
	}

	private static function splitUrl( $url ) {
		$fragment = '';
		$hashPos  = strpos( $url, '#' );
		if ( false !== $hashPos ) {
			$fragment = substr( $url, $hashPos + 1 );
			$url      = substr( $url, 0, $hashPos );
		}

		$query    = '';
		$queryPos = strpos( $url, '?' );
		if ( false !== $queryPos ) {
			$query = substr( $url, $queryPos + 1 );
			$url   = substr( $url, 0, $queryPos );
		}

		return [ $url, $query, $fragment ];
	}

	private static function stripOwnedParams( $query ) {
		if ( '' === $query ) {
			return [];
		}

		$kept = [];

		foreach ( explode( '&', $query ) as $segment ) {
			if ( '' === $segment ) {
				continue;
			}

			$key = strtolower( self::segmentKey( $segment ) );

			if ( self::isOwnedParam( $key ) ) {
				continue;
			}

			$kept[] = $segment;
		}

		return $kept;
	}

	private static function segmentKey( $segment ) {
		$eqPos = strpos( $segment, '=' );

		return false === $eqPos ? $segment : substr( $segment, 0, $eqPos );
	}

	private static function isOwnedParam( $key ) {
		if ( in_array( $key, self::OWNED_PARAMS, true ) ) {
			return true;
		}

		foreach ( self::OWNED_PARAM_PREFIXES as $prefix ) {
			if ( 0 === strpos( $key, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	private static function extractHost( $url ) {
		$host = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url, PHP_URL_HOST ) : null;

		if ( ! is_string( $host ) || '' === $host ) {
			$host = parse_url( $url, PHP_URL_HOST );
		}

		return is_string( $host ) ? strtolower( $host ) : '';
	}

	private static function isWpmlHost( $host ) {
		if ( '' === $host ) {
			return false;
		}

		if ( 0 === strpos( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}

		return 'wpml.org' === $host || self::endsWith( $host, '.wpml.org' );
	}

	private static function endsWith( $haystack, $needle ) {
		$length = strlen( $needle );

		if ( 0 === $length ) {
			return true;
		}

		return substr( $haystack, - $length ) === $needle;
	}

	private static function slug( $value ) {
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return '';
		}

		$value = strtolower( (string) $value );
		$value = preg_replace( '/[^a-z0-9]+/', '-', $value );

		return trim( $value, '-' );
	}

	private static function version() {
		return defined( 'ICL_SITEPRESS_VERSION' ) ? (string) ICL_SITEPRESS_VERSION : '';
	}
}
