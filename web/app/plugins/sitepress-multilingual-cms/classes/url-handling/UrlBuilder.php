<?php

namespace WPML\UrlHandling;

final class UrlBuilder {

	const REPLACEABLE = [ 'user', 'pass', 'port', 'path', 'query', 'fragment' ];

	public static function build( $url, array $parts = [] ): string {
		if ( ! is_array( $url ) ) {
			$url = parse_url( (string) $url );
			$url = is_array( $url ) ? $url : [];
		}

		$url['query']   = isset( $url['query'] ) && is_string( $url['query'] ) ? $url['query'] : null;
		$parts['query'] = isset( $parts['query'] ) && is_string( $parts['query'] ) ? $parts['query'] : null;

		foreach ( [ 'scheme', 'host' ] as $part ) {
			if ( isset( $parts[ $part ] ) ) {
				$url[ $part ] = $parts[ $part ];
			}
		}

		foreach ( self::REPLACEABLE as $part ) {
			if ( isset( $parts[ $part ] ) ) {
				$url[ $part ] = $parts[ $part ];
			}
		}

		if ( isset( $url['path'] ) && '' !== $url['path'] && '/' !== substr( (string) $url['path'], 0, 1 ) ) {
			$url['path'] = '/' . $url['path'];
		}

		$built = '';

		if ( ! empty( $url['scheme'] ) ) {
			$built .= $url['scheme'] . '://';
		}

		if ( ! empty( $url['user'] ) ) {
			$built .= $url['user'];

			if ( isset( $url['pass'] ) ) {
				$built .= ':' . $url['pass'];
			}

			$built .= '@';
		}

		if ( ! empty( $url['host'] ) ) {
			$built .= $url['host'];
		}

		if ( ! empty( $url['port'] ) ) {
			$built .= ':' . $url['port'];
		}

		if ( ! empty( $url['path'] ) ) {
			$built .= $url['path'];
		}

		if ( ! empty( $url['query'] ) ) {
			$built .= '?' . $url['query'];
		}

		if ( ! empty( $url['fragment'] ) ) {
			$built .= '#' . $url['fragment'];
		}

		return $built;
	}
}
