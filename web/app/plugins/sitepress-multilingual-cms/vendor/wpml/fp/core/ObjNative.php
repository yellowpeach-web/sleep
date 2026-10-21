<?php

namespace WPML\FP;

class ObjNative {

	public static function propOr( $default, $key, $item ) {
		if ( is_array( $item ) ) {
			if ( null === $key ) {
				return $default;
			}

			return array_key_exists( $key, $item ) ? $item[ $key ] : $default;
		}

		if ( is_object( $item ) ) {
			if ( property_exists( $item, (string) $key ) || isset( $item->$key ) ) {
				return $item->$key;
			}
			if ( is_numeric( $key ) ) {
				return self::propOr( $default, $key, (array) $item );
			}
		}

		return $default;
	}
}
