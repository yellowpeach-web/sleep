<?php

namespace WPML\FP;

use WPML\Collect\Support\Traits\Macroable;

class Str {
	use Macroable;


	public static function startsWith( $needle = null, $haystack = null ) {
		if ( func_num_args() < 2 || Fns::__ === $needle || Fns::__ === $haystack ) {
			return call_user_func_array( self::$macros['startsWith'], func_get_args() );
		}

		return 0 === self::position( $needle, $haystack );
	}

	public static function includes( $needle = null, $haystack = null ) {
		if ( func_num_args() < 2 || Fns::__ === $needle || Fns::__ === $haystack ) {
			return call_user_func_array( self::$macros['includes'], func_get_args() );
		}

		return false !== self::position( $needle, $haystack );
	}

	private static function position( $needle, $haystack ) {
		$haystack = is_null( $haystack ) ? '' : $haystack;

		if ( function_exists( 'mb_strpos' ) ) {
			return mb_strpos( $haystack, $needle );
		}

		return strpos( $haystack, $needle );
	}

	public static function init() {

		self::macro( 'split', curryN( 2, 'explode' ) );

		self::macro( 'parse', curryN( 1, function( $string ) {
			parse_str( $string, $parsedString );
			return $parsedString;
		} ) );

		self::macro( 'trim', curryN( 2, flip( 'trim' ) ) );

		self::macro( 'trimPrefix', curryN( 2, function( $prefix, $str ) {
			return $prefix && self::pos( $prefix, $str ) === 0 ? self::sub( self::len( $prefix ), $str ) : $str;
		} ) );

		self::macro( 'concat', curryN( 2, function ( $a, $b ) {
			return $a . $b;
		} ) );

		self::macro( 'sub', curryN( 2, function( $start, $string ) {
			if ( function_exists( 'mb_substr' ) ) {
				return mb_substr( $string, $start );
			}

			return substr( $string, $start );
		} ) );

		self::macro( 'tail', self::sub( 1 ) );

		self::macro( 'pos', curryN( 2, function ( $needle, $haystack ) {
			return self::position( $needle, $haystack );
		} ) );

		self::macro( 'startsWith', curryN( 2, function ( $needle, $haystack ) {
			return self::startsWith( $needle, $haystack );
		} ) );

		self::macro( 'endsWith', curryN( 2, function ( $find, $s ) {
			return self::sub( - self::len( $find ), $s ) === $find;
		} ) );

		self::macro( 'includes', curryN( 2, function ( $needle, $haystack ) {
			return self::includes( $needle, $haystack );
		} ) );

		self::macro( 'len', curryN( 1, function_exists( 'mb_strlen' ) ? 'mb_strlen' : 'strlen' ) );


		self::macro( 'replace', curryN( 3, function ( $search, $replace, $subject ) {
			return str_replace( $search, $replace, $subject );
		} ) );

		self::macro( 'pregReplace', curryN( 3, function ( $pattern, $replace, $subject ) {
			return preg_replace( $pattern, $replace, $subject );
		} ) );

		self::macro( 'match', curryN( 2, function ( $pattern, $subject ) {
			$matches = [];

			if ( ! is_string( $pattern ) || ( ! is_string( $subject ) && ! is_numeric( $subject ) ) ) {
				return false;
			}

			return preg_match( $pattern, (string) $subject, $matches ) ? $matches : [];
		} ) );

		self::macro( 'matchAll', curryN( 2, function ( $pattern, $subject ) {
			$matches = [];

			return preg_match_all( $pattern, $subject, $matches, PREG_SET_ORDER ) ? $matches : [];
		} ) );

		self::macro( 'wrap', curryN( 3, function ( $before, $after, $string ) {
			return $before . $string . $after;
		} ) );

		self::macro( 'toUpper', curryN( 1, 'strtoupper' ) );

		self::macro( 'toLower', curryN( 1, 'strtolower' ) );
	}

	public static function truncate_bytes( $string, $max_bytes, $max_characters = null ) {
		if ( $max_characters !== null ) {
			$string = mb_substr( $string, 0, $max_characters );
		} else {
			$string = mb_substr( $string, 0, $max_bytes );
			$max_characters = mb_strlen( $string );
		}

		if ( strlen( $string ) > $max_bytes ) {
			return static::truncate_bytes( $string, $max_bytes, $max_characters - 1 );
		}

		return $string;
	}
}

Str::init();
