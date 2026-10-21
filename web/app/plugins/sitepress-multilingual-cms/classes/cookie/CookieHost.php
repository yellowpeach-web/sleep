<?php

namespace WPML\Cookie;

class CookieHost {

	public static function get() {
		$host = self::strip_port( self::read_request_host() );

		return strpos( $host, '.' ) === false ? '' : $host;
	}

	private static function read_request_host() {
		if ( isset( $_SERVER['HTTP_HOST'] ) && '' !== $_SERVER['HTTP_HOST'] ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
		}

		if ( isset( $_SERVER['SERVER_NAME'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) );
		}

		return '';
	}

	private static function strip_port( $host ) {
		return (string) preg_replace( '@:\d+$@', '', trim( $host ) );
	}
}
