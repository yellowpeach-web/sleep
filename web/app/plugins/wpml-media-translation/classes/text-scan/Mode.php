<?php

namespace WPML\Media\TextScan;

class Mode {

	const OPTION = '_wpml_media_text_scan_settings';

	const OFF    = 'off';
	const LIKELY = 'likely';
	const ALL    = 'all';

	public static function get() {
		$stored = get_option( self::OPTION );

		if ( is_array( $stored ) && isset( $stored['mode'] ) && self::isValid( $stored['mode'] ) ) {
			return $stored['mode'];
		}

		return self::OFF;
	}

	public static function set( $mode ) {
		if ( ! self::isValid( $mode ) ) {
			return false;
		}

		update_option( self::OPTION, [ 'mode' => $mode ], false );

		return true;
	}

	public static function isValid( $mode ) {
		return is_string( $mode ) && in_array( $mode, [ self::OFF, self::LIKELY, self::ALL ], true );
	}
}
