<?php

namespace ACFML;

use WPML\WP\OptionManager;

class Options {

	const GROUP = 'acfml';

	public static function get( $key, $default = null ) {
		return ( new OptionManager() )->get( self::GROUP, $key, $default );
	}

	public static function set( $key, $value ) {
		( new OptionManager() )->set( self::GROUP, $key, $value );
	}
}
