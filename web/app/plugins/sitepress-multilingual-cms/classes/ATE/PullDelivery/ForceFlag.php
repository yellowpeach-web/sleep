<?php

namespace WPML\TM\ATE\PullDelivery;

class ForceFlag {

	const AFFIRMATIVE = [ '1', 'true', 'on', 'yes' ];

	public static function wasRequested( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return $value > 0;
		}

		if ( ! is_string( $value ) ) {
			return false;
		}

		return in_array( strtolower( trim( $value ) ), self::AFFIRMATIVE, true );
	}

	public static function fromPayload( $payload, $key = 'force' ) {
		if ( ! is_array( $payload ) || ! isset( $payload[ $key ] ) ) {
			return false;
		}

		return self::wasRequested( $payload[ $key ] );
	}
}
