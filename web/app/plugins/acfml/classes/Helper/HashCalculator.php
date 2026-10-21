<?php

namespace ACFML\Helper;

use WPML\FP\Obj;

class HashCalculator {

	public static function calculate( $value ) {
		$value = self::normalize( $value );

		if ( is_string( $value ) ) {
			return self::hash( $value );
		} elseif ( is_numeric( $value ) ) {
			return self::hash( (string) $value );
		} elseif ( is_array( $value ) && self::getID( $value ) ) {
			return self::hash( (string) self::getID( $value ) );
		} elseif ( ! $value ) {
			return '';
		}

		return self::hashArray( $value );
	}

	public static function calculateRows( $value ) {
		$value = self::normalize( $value );

		if ( ! $value ) {
			return [];
		}

		$holdsRows = is_array( $value ) && ! self::getID( $value ) && self::isArrayOfArrays( $value );

		return $holdsRows
			? wpml_collect( $value )->map( [ self::class, 'calculate' ] )->values()->toArray()
			: [ self::calculate( $value ) ];
	}

	private static function hash( $value ) {
		return md5( $value );
	}

	private static function getID( array $value ) {
		return Obj::prop( 'ID', $value );
	}

	private static function hashArray( $array ) {
		return self::isArrayOfArrays( $array ) ? self::hashArrayOfArrays( $array ) : self::hashAssociativeArray( $array );
	}

	private static function isArrayOfArrays( $array ) {
		$intIndexArrayValue = function ( $val, $index ) {
			return is_int( $index ) && is_array( $val );
		};

		return count( $array ) === wpml_collect( $array )->filter( $intIndexArrayValue )->count();
	}

	private static function hashArrayOfArrays( $array ) {
		$hashes = wpml_collect( $array )->map( [ self::class, 'calculate' ] )->toArray();
		sort( $hashes );
		return self::hash( implode( $hashes ) );
	}

	private static function hashAssociativeArray( $array ) {
		ksort( $array );
		$hashes = wpml_collect( $array )->map( [ self::class, 'calculate' ] )->toArray();
		return self::hash( implode( $hashes ) );
	}

	public static function normalize( $value ) {
		if ( is_bool( $value ) ) {
			$value = (int) $value;
		} elseif ( is_object( $value ) ) {
			$value = (array) $value;
		}

		return $value;
	}
}
