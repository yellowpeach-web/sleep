<?php

namespace OTGS\Installer\Templates\Repository;

class Url {

	public static function withHost( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return null;
		}

		$host = parse_url( $url, PHP_URL_HOST );

		return is_string( $host ) && '' !== $host ? $url : null;
	}

	public static function append( $base, $path ) {
		return self::withHost( $base ) ? $base . $path : null;
	}
}
