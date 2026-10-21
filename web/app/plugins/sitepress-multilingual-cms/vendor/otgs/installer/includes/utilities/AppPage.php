<?php

namespace OTGS\Installer;

class AppPage {

	const WPML_APP_ORIGIN = 'https://app.wpml.org';

	private static $wpmlHosts = [ 'wpml.org', 'www.wpml.org' ];

	public static function url( $productUrl, $path ) {
		$path = trim( (string) $path, '/' );
		$host = strtolower( (string) parse_url( (string) $productUrl, PHP_URL_HOST ) );

		if ( in_array( $host, self::$wpmlHosts, true ) ) {
			return self::WPML_APP_ORIGIN . '/' . $path;
		}

		return rtrim( (string) $productUrl, '/' ) . '/' . $path . '/';
	}
}
