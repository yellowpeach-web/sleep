<?php

class OTGS_Installer_Serialized {

	const STDCLASS_ONLY = array( 'stdClass' );

	public static function decode( $data, $allowed_classes = self::STDCLASS_ONLY ) {
		$value = @unserialize( (string) $data, array( 'allowed_classes' => $allowed_classes ) );

		return self::names_a_refused_class( $value ) ? false : $value;
	}

	private static function names_a_refused_class( $value ) {
		if ( $value instanceof \__PHP_Incomplete_Class ) {
			return true;
		}
		if ( is_object( $value ) ) {
			$value = get_object_vars( $value );
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( self::names_a_refused_class( $item ) ) {
					return true;
				}
			}
		}

		return false;
	}

	public static function maybe_decode( $value, $allowed_classes = self::STDCLASS_ONLY ) {
		if ( ! is_string( $value ) || ! self::looks_serialized( $value ) ) {
			return $value;
		}

		return self::decode( $value, $allowed_classes );
	}

	private static function looks_serialized( $data ) {
		$data = trim( $data );
		if ( 'N;' === $data ) {
			return true;
		}
		if ( strlen( $data ) < 4 || ':' !== $data[1] ) {
			return false;
		}
		$last = substr( $data, -1 );
		switch ( $data[0] ) {
			case 's':
				return '"' === substr( $data, -2, 1 ) && ';' === $last;
			case 'a':
			case 'O':
			case 'C':
			case 'E':
				return '}' === $last;
			case 'b':
			case 'i':
			case 'd':
				return ';' === $last;
		}

		return false;
	}
}
