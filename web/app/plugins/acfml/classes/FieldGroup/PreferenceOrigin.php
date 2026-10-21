<?php

namespace ACFML\FieldGroup;

class PreferenceOrigin {

	const OPTION = 'acfml_preferences_set_on_wpml_settings';

	public static function stampWpmlSettings( $fieldName ) {
		$fieldName = (string) $fieldName;
		$names     = self::read();

		if ( '' === $fieldName || isset( $names[ $fieldName ] ) ) {
			return;
		}

		$names[ $fieldName ] = true;

		self::write( $names );
	}

	public static function clear( $fieldName ) {
		$fieldName = (string) $fieldName;
		$names     = self::read();

		if ( '' === $fieldName || ! isset( $names[ $fieldName ] ) ) {
			return;
		}

		unset( $names[ $fieldName ] );

		self::write( $names );
	}

	public static function isSetOnWpmlSettings( $fieldName ) {
		$names = self::read();

		return isset( $names[ (string) $fieldName ] );
	}

	private static function read() {
		$stored = get_option( self::OPTION, [] );

		return is_array( $stored ) ? $stored : [];
	}

	private static function write( array $names ) {
		update_option( self::OPTION, $names, true );
	}
}
