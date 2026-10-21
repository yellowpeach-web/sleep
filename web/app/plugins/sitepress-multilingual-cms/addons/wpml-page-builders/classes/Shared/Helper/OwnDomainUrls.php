<?php

namespace WPML\PB\Helper;

class OwnDomainUrls {

	const ABSOLUTE_URL = '#https?://[^\s\'"()]+#i';

	const ORIGIN = '#^https?://[^/?\#]*#i';

	public static function makeRelative( $text, array $hosts ) {
		return (string) preg_replace_callback(
			self::ABSOLUTE_URL,
			function ( $matches ) use ( $hosts ) {
				return self::dropOwnHost( $matches[0], $hosts );
			},
			$text
		);
	}

	private static function dropOwnHost( $url, array $hosts ) {
		$origin = self::originOf( $url );

		if ( ! self::isOwnHost( $origin, $hosts ) ) {
			return $url;
		}

		return self::everythingAfterOrigin( $url, $origin );
	}

	private static function originOf( $url ) {
		return preg_match( self::ORIGIN, $url, $matches ) ? $matches[0] : '';
	}

	private static function isOwnHost( $origin, array $hosts ) {
		$host = $origin ? wp_parse_url( $origin, PHP_URL_HOST ) : null;

		return $host && in_array( strtolower( $host ), $hosts, true );
	}

	private static function everythingAfterOrigin( $url, $origin ) {
		$path = substr( $url, strlen( $origin ) );

		return '' === $path ? '/' : $path;
	}
}
