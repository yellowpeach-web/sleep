<?php

namespace WPML\FP;

class StrNative {

	public static function endsWith( $find, $s ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $s, - mb_strlen( $find ) ) === $find;
		}

		return substr( $s, - strlen( $find ) ) === $find;
	}

	public static function includes( $needle, $haystack ) {
		$haystack = null === $haystack ? '' : $haystack;

		if ( function_exists( 'mb_strpos' ) ) {
			return false !== mb_strpos( $haystack, $needle );
		}

		return false !== strpos( $haystack, $needle );
	}

	public static function len( $s ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $s ) : strlen( $s );
	}
}
