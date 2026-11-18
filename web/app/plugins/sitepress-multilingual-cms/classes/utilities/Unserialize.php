<?php

namespace WPML\Utils;

class Unserialize {

	public static function maybe( $value, $allowedClasses = false ) {
		if ( ! self::isSerialized( $value ) ) {
			return $value;
		}

		return @unserialize( trim( $value ), [ 'allowed_classes' => $allowedClasses ] );
	}

	public static function isSerialized( $value ) {
		if ( ! is_string( $value ) ) {
			return false;
		}
		$value = trim( $value );
		if ( 'N;' === $value ) {
			return true;
		}
		if ( strlen( $value ) < 4 || ':' !== $value[1] ) {
			return false;
		}
		$last = substr( $value, -1 );
		if ( ';' !== $last && '}' !== $last ) {
			return false;
		}
		$semicolon = strpos( $value, ';' );
		$brace     = strpos( $value, '}' );
		if ( false === $semicolon && false === $brace ) {
			return false;
		}
		if ( ( false !== $semicolon && $semicolon < 3 ) || ( false !== $brace && $brace < 4 ) ) {
			return false;
		}
		$token = $value[0];
		switch ( $token ) {
			case 's':
				if ( '"' !== substr( $value, -2, 1 ) ) {
					return false;
				}
			case 'a':
			case 'O':
			case 'E':
				return (bool) preg_match( "/^{$token}:[0-9]+:/s", $value );
			case 'b':
			case 'i':
			case 'd':
				return (bool) preg_match( "/^{$token}:[0-9.E+-]+;$/", $value );
		}

		return false;
	}
}
