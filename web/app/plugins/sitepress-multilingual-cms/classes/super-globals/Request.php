<?php

namespace WPML\SuperGlobals;

use WPML\API\Sanitize;

class Request {

	public static function page() {
		return self::param( 'page' );
	}

	public static function param( $key ) {
		if ( ! isset( $_GET[ $key ] ) || ! is_string( $_GET[ $key ] ) ) {
			return '';
		}

		return (string) Sanitize::string( $_GET[ $key ] );
	}
}
