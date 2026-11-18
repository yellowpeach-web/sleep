<?php

namespace WPML\FP;

use WPML\Collect\Support\Traits\Macroable;

class Lens {

	use Macroable;

	public static function init() {

		self::macro( 'iso', curryN( 2, function( $get, $reverseGet ) {
			return function ( $toFunctorFn ) use ( $get, $reverseGet ) {
				return function ( $target ) use ( $toFunctorFn, $get, $reverseGet ) {
					$value = $get( $target );
					return Fns::map( $reverseGet, $toFunctorFn( $value ) );
				};
			};
		} ) );

		self::macro( 'isoIdentity', function() {
			return self::iso( Fns::identity(), Fns::identity() );
		} );

		self::macro( 'isoUnserialized', function() {
			return self::iso( 'unserialize', 'serialize' );
		} );

		self::macro( 'isoJsonDecoded', function() {
			return self::iso( 'json_decode', 'json_encode' );
		} );

		self::macro( 'isoUrlDecoded', function() {
			return self::iso( 'urldecode', 'urlencode' );
		} );

		self::macro( 'isoBase64Decoded', function() {
			return self::iso( 'base64_decode', 'base64_encode' );
		} );

		self::macro( 'isoParsedUrl', function() {
			return self::iso( 'parse_url', [ self::class, 'buildUrl' ] );
		} );

		self::macro( 'isoParsedQuery', function() {
			return self::iso( Str::parse(), 'http_build_query' );
		} );
	}

	public static function buildUrl( $parts ) {
		$parts = is_array( $parts ) ? $parts : [];
		if ( isset( $parts['query'] ) && ! is_string( $parts['query'] ) ) {
			unset( $parts['query'] );
		}
		if ( isset( $parts['path'] ) && '' !== $parts['path'] && '/' !== substr( (string) $parts['path'], 0, 1 ) ) {
			$parts['path'] = '/' . $parts['path'];
		}

		$url = '';
		if ( ! empty( $parts['scheme'] ) ) {
			$url .= $parts['scheme'] . '://';
		}
		if ( ! empty( $parts['user'] ) ) {
			$url .= $parts['user'];
			if ( isset( $parts['pass'] ) ) {
				$url .= ':' . $parts['pass'];
			}
			$url .= '@';
		}
		if ( ! empty( $parts['host'] ) ) {
			$url .= $parts['host'];
		}
		if ( ! empty( $parts['port'] ) ) {
			$url .= ':' . $parts['port'];
		}
		if ( ! empty( $parts['path'] ) ) {
			$url .= $parts['path'];
		}
		if ( ! empty( $parts['query'] ) ) {
			$url .= '?' . $parts['query'];
		}
		if ( ! empty( $parts['fragment'] ) ) {
			$url .= '#' . $parts['fragment'];
		}

		return $url;
	}
}

Lens::init();
