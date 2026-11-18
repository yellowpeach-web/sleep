<?php

namespace WPML\Collect\Support;

use ArrayAccess;
use WPML\Collect\Support\Traits\Macroable;

if ( ! class_exists( 'WPML\\Collect\\Support\\HelperFunctions', false ) ) {
	require_once __DIR__ . '/../../HelperFunctions.php';
}

class Arr {

	use Macroable;

	public static function accessible( $value ) {
		return is_array( $value ) || $value instanceof ArrayAccess;
	}

	public static function exists( $array, $key ) {
		if ( $array instanceof ArrayAccess ) {
			return $array->offsetExists( $key );
		}

		return array_key_exists( $key, $array );
	}

	public static function get( $array, $key, $default = null ) {
		if ( ! static::accessible( $array ) ) {
			return HelperFunctions::value( $default );
		}

		if ( is_null( $key ) ) {
			return $array;
		}

		if ( static::exists( $array, $key ) ) {
			return $array[ $key ];
		}

		foreach ( explode( '.', $key ) as $segment ) {
			if ( ! static::accessible( $array ) || ! static::exists( $array, $segment ) ) {
				return HelperFunctions::value( $default );
			}

			$array = $array[ $segment ];
		}

		return $array;
	}

	public static function has( $array, $keys ) {
		if ( is_null( $keys ) ) {
			return false;
		}

		$keys = (array) $keys;

		if ( ! $array || [] === $keys ) {
			return false;
		}

		foreach ( $keys as $key ) {
			if ( static::exists( $array, $key ) ) {
				continue;
			}

			$level = $array;

			foreach ( explode( '.', $key ) as $segment ) {
				if ( ! static::accessible( $level ) || ! static::exists( $level, $segment ) ) {
					return false;
				}

				$level = $level[ $segment ];
			}
		}

		return true;
	}

	public static function set( &$array, $key, $value ) {
		if ( is_null( $key ) ) {
			$array = $value;

			return $array;
		}

		$segments = explode( '.', $key );
		$last     = array_pop( $segments );

		foreach ( $segments as $segment ) {
			if ( ! isset( $array[ $segment ] ) || ! is_array( $array[ $segment ] ) ) {
				$array[ $segment ] = [];
			}

			$array = &$array[ $segment ];
		}

		$array[ $last ] = $value;

		return $array;
	}

	public static function add( $array, $key, $value ) {
		if ( is_null( static::get( $array, $key ) ) ) {
			static::set( $array, $key, $value );
		}

		return $array;
	}

	public static function forget( &$array, $keys ) {
		$root = &$array;
		$keys = (array) $keys;

		if ( count( $keys ) === 0 ) {
			return;
		}

		foreach ( $keys as $key ) {
			if ( static::exists( $array, $key ) ) {
				unset( $array[ $key ] );

				continue;
			}

			$segments = explode( '.', $key );
			$array    = &$root;

			while ( count( $segments ) > 1 ) {
				$segment = array_shift( $segments );

				if ( ! isset( $array[ $segment ] ) || ! is_array( $array[ $segment ] ) ) {
					continue 2;
				}

				$array = &$array[ $segment ];
			}

			unset( $array[ array_shift( $segments ) ] );
		}
	}

	public static function pull( &$array, $key, $default = null ) {
		$value = static::get( $array, $key, $default );

		static::forget( $array, $key );

		return $value;
	}

	public static function except( $array, $keys ) {
		static::forget( $array, $keys );

		return $array;
	}

	public static function only( $array, $keys ) {
		return array_intersect_key( $array, array_flip( (array) $keys ) );
	}

	public static function first( $array, ?callable $callback = null, $default = null ) {
		foreach ( $array as $key => $value ) {
			if ( is_null( $callback ) || call_user_func( $callback, $value, $key ) ) {
				return $value;
			}
		}

		return HelperFunctions::value( $default );
	}

	public static function last( $array, ?callable $callback = null, $default = null ) {
		if ( is_null( $callback ) ) {
			return empty( $array ) ? HelperFunctions::value( $default ) : end( $array );
		}

		return static::first( array_reverse( $array, true ), $callback, $default );
	}

	public static function where( $array, callable $callback ) {
		return array_filter( $array, $callback, ARRAY_FILTER_USE_BOTH );
	}

	public static function pluck( $array, $value, $key = null ) {
		list( $value, $key ) = static::explodePluckParameters( $value, $key );

		$plucked = [];

		foreach ( $array as $item ) {
			$itemValue = HelperFunctions::dataGet( $item, $value );

			if ( is_null( $key ) ) {
				$plucked[] = $itemValue;
			} else {
				$plucked[ HelperFunctions::dataGet( $item, $key ) ] = $itemValue;
			}
		}

		return $plucked;
	}

	protected static function explodePluckParameters( $value, $key ) {
		if ( is_string( $value ) ) {
			$value = explode( '.', $value );
		}

		if ( ! is_null( $key ) && ! is_array( $key ) ) {
			$key = explode( '.', $key );
		}

		return [ $value, $key ];
	}

	public static function collapse( $array ) {
		$merged = [];

		foreach ( $array as $values ) {
			if ( $values instanceof Collection ) {
				$values = $values->all();
			} elseif ( ! is_array( $values ) ) {
				continue;
			}

			$merged = array_merge( $merged, $values );
		}

		return $merged;
	}

	public static function flatten( $array, $depth = INF ) {
		$flat = [];

		foreach ( $array as $item ) {
			if ( $item instanceof Collection ) {
				$item = $item->all();
			}

			if ( ! is_array( $item ) ) {
				$flat[] = $item;
			} elseif ( 1 === $depth ) {
				$flat = array_merge( $flat, array_values( $item ) );
			} else {
				$flat = array_merge( $flat, static::flatten( $item, $depth - 1 ) );
			}
		}

		return $flat;
	}

	public static function dot( $array, $prepend = '' ) {
		$flat = [];

		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) && ! empty( $value ) ) {
				$flat = array_merge( $flat, static::dot( $value, $prepend . $key . '.' ) );
			} else {
				$flat[ $prepend . $key ] = $value;
			}
		}

		return $flat;
	}

	public static function divide( $array ) {
		return [ array_keys( $array ), array_values( $array ) ];
	}

	public static function isAssoc( array $array ) {
		$keys = array_keys( $array );

		return array_keys( $keys ) !== $keys;
	}

	public static function prepend( $array, $value, $key = null ) {
		if ( is_null( $key ) ) {
			array_unshift( $array, $value );

			return $array;
		}

		return [ $key => $value ] + $array;
	}

	public static function sort( $array, $callback ) {
		return Collection::make( $array )->sortBy( $callback )->all();
	}

	public static function sortRecursive( $array ) {
		foreach ( $array as &$value ) {
			if ( is_array( $value ) ) {
				$value = static::sortRecursive( $value );
			}
		}

		if ( static::isAssoc( $array ) ) {
			ksort( $array );
		} else {
			sort( $array );
		}

		return $array;
	}

	public static function crossJoin( ...$arrays ) {
		$combinations = [ [] ];

		foreach ( $arrays as $index => $array ) {
			$longer = [];

			foreach ( $combinations as $combination ) {
				foreach ( $array as $item ) {
					$combination[ $index ] = $item;
					$longer[]              = $combination;
				}
			}

			$combinations = $longer;
		}

		return $combinations;
	}
}
