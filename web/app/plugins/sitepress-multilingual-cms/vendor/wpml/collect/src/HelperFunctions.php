<?php

namespace WPML\Collect\Support;

use Closure;

final class HelperFunctions {

	public static function dataGet( $target, $key, $default = null ) {
		if ( is_null( $key ) ) {
			return $target;
		}

		$segments = is_array( $key ) ? $key : explode( '.', $key );

		while ( ( $segment = array_shift( $segments ) ) !== null ) {
			if ( '*' === $segment ) {
				if ( $target instanceof Collection ) {
					$target = $target->all();
				} elseif ( ! is_array( $target ) ) {
					return static::value( $default );
				}

				$found = Arr::pluck( $target, $segments );

				return in_array( '*', $segments ) ? Arr::collapse( $found ) : $found;
			}

			if ( Arr::accessible( $target ) && Arr::exists( $target, $segment ) ) {
				$target = $target[ $segment ];
			} elseif ( is_object( $target ) && isset( $target->{$segment} ) ) {
				$target = $target->{$segment};
			} else {
				return static::value( $default );
			}
		}

		return $target;
	}

	public static function value( $value, ...$args ) {
		return $value instanceof Closure ? $value( ...$args ) : $value;
	}

	public static function with( $object ) {
		return $object;
	}
}
