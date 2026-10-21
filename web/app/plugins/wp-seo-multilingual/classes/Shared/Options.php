<?php

namespace WPML\WPSEO\Shared;

use WPML\WP\OptionManager;

class Options {

	const GROUP = 'wpml-seo';

	public static function get( $key, $defaultValue = null ) {
		return ( new OptionManager() )->get( self::GROUP, $key, $defaultValue );
	}

	public static function set( $key, $value ) {
		( new OptionManager() )->set( self::GROUP, $key, $value );
	}
}
