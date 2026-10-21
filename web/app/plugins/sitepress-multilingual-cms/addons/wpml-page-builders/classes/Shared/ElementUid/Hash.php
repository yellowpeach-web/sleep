<?php

namespace WPML\PB\ElementUid;

class Hash {

	public static function of( $data ) {
		return md5( serialize( self::canonical( $data ) ) );
	}

	private static function canonical( $value ) {
		if ( is_object( $value ) ) {
			$value = get_object_vars( $value );
		}

		if ( ! is_array( $value ) ) {
			return $value;
		}

		$value = array_map( [ self::class, 'canonical' ], $value );

		if ( ! self::isList( $value ) ) {
			ksort( $value, SORT_STRING );
		}

		return $value;
	}

	private static function isList( array $value ) {
		$expected = 0;

		foreach ( array_keys( $value ) as $key ) {
			if ( $key !== $expected++ ) {
				return false;
			}
		}

		return true;
	}
}
