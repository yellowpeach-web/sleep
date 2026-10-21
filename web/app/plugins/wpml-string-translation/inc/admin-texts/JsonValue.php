<?php

namespace WPML\ST\AdminTexts;

class JsonValue {

	public static function decode( $value ) {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$trimmed = trim( $value );
		if ( '' === $trimmed || ! in_array( $trimmed[0], [ '[', '{' ], true ) ) {
			return null;
		}

		$decoded = json_decode( $trimmed );
		if ( ( ! is_array( $decoded ) && ! is_object( $decoded ) ) || ! self::hasText( $decoded ) ) {
			return null;
		}

		return $decoded;
	}

	public static function toArray( $value ) {
		if ( null === self::decode( $value ) ) {
			return null;
		}

		return json_decode( trim( $value ), true );
	}

	public static function encode( $original, $translated ) {
		$flags = JSON_PRESERVE_ZERO_FRACTION;

		if ( json_encode( self::decode( $original ), $flags ) === json_encode( $translated, $flags ) ) {
			return $original;
		}

		if ( false === strpos( $original, '\\/' ) ) {
			$flags |= JSON_UNESCAPED_SLASHES;
		}
		if ( ! preg_match( '/\\\\u[0-9a-fA-F]{4}/', $original ) ) {
			$flags |= JSON_UNESCAPED_UNICODE;
		}
		if ( false !== strpos( trim( $original ), "\n" ) ) {
			$flags |= JSON_PRETTY_PRINT;
		}

		$encoded = json_encode( $translated, $flags );

		return false === $encoded ? $original : $encoded;
	}

	private static function hasText( $value ) {
		if ( is_string( $value ) ) {
			return '' !== trim( $value );
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			foreach ( $value as $item ) {
				if ( self::hasText( $item ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
