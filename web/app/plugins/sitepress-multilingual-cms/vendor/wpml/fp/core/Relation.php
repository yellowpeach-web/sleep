<?php

namespace WPML\FP;

use WPML\Collect\Support\Traits\Macroable;

class Relation {

	use Macroable;


	public static function equals( $a = null, $b = null ) {
		if ( func_num_args() < 2 || Fns::__ === $a || Fns::__ === $b ) {
			return call_user_func_array( self::$macros['equals'], func_get_args() );
		}

		return $a === $b;
	}

	public static function propEq( $prop = null, $value = null, $obj = null ) {
		if ( func_num_args() < 3 || Fns::__ === $prop || Fns::__ === $value || Fns::__ === $obj ) {
			return call_user_func_array( self::$macros['propEq'], func_get_args() );
		}

		return Obj::prop( $prop, $obj ) === $value;
	}

	public static function init() {

		self::macro( 'equals', curryN( 2, function ( $a, $b ) {
			return self::equals( $a, $b );
		} ) );

		self::macro( 'lt', curryN( 2, function ( $a, $b ) {
			if ( is_string( $a ) && is_string( $b ) ) {
				return strcmp( $a, $b ) < 0;
			}

			return $a < $b;
		} ) );

		self::macro( 'gt', curryN( 2, function ( $a, $b ) {
			return self::lt( $b, $a );
		} ) );

		self::macro( 'lte', curryN( 2, function ( $a, $b ) {
			return ! self::gt( $a, $b );
		} ) );

		self::macro( 'gte', curryN( 2, function ( $a, $b ) {
			return ! self::lt( $a, $b );
		} ) );

		self::macro( 'propEq', curryN( 3, function ( $prop, $value, $obj ) {
			return self::propEq( $prop, $value, $obj );
		} ) );

		self::macro( 'sortWith', curryN( 2, function ( $comparators, $array ) {
			$compareFn = function( $a, $b ) use ( $comparators ) {
				foreach ( $comparators as $comparator ) {
					$v = $comparator( $a, $b );
					if ( $v !== 0 ) {
						return $v;
					}
				}
				return 0;
			};
			return Lst::sort( $compareFn, $array );
		}));

	}
}

Relation::init();
