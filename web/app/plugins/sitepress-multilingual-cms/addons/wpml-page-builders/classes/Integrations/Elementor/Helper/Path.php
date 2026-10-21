<?php

namespace WPML\PB\Elementor\Helper;

use WPML\FP\Obj;

class Path {

	public static function prop( $key, $item ) {
		return is_array( $item ) ? $item[ $key ] ?? null : Obj::prop( $key, $item );
	}

	public static function get( array $path, $item ) {
		$value = $item;

		foreach ( $path as $key ) {
			$value = is_array( $value ) ? $value[ $key ] ?? null : Obj::prop( $key, $value );

			if ( null === $value ) {
				return null;
			}
		}

		return $value;
	}

	public static function propEq( $key, $value ) {
		return function ( $item ) use ( $key, $value ) {
			return self::prop( $key, $item ) === $value;
		};
	}
}
