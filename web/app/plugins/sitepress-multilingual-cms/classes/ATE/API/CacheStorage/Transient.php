<?php

namespace WPML\TM\ATE\API\CacheStorage;

use WPML\LIB\WP\Transient as WPTransient;

class Transient implements Storage {

	const TTL = 3600 * 24;

	public function get( $key, $default = null ) {
		return WPTransient::getOr( $key, $default );
	}

	public function save( $key, $value ) {
		WPTransient::set( $key, $value, self::TTL );
	}

	public function delete( $key ) {
		WPTransient::delete( $key );
	}

}