<?php

namespace WPML\TM\ATE\Handshake;

class Clock {

	private static $fixed = null;

	public static function now() {
		return (int) floor( self::exact() );
	}

	public static function exact() {
		$now = null === self::$fixed ? microtime( true ) : self::$fixed;

		return $now + self::offset();
	}

	private static function offset() {
		$offset = apply_filters( 'wpml_ate_handshake_clock_offset', 0 );

		return is_numeric( $offset ) ? (float) $offset : 0.0;
	}

	public static function fixForTests( $time ) {
		self::$fixed = null === $time ? null : (float) $time;
	}
}
