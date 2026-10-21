<?php

namespace WPML\TM\ATE\PullDelivery;

use WPML\FP\Obj;
use WPML\LIB\WP\Option;

class ApplyBackoff {

	const OPTION = 'wpml_pull_delivery_apply_backoff';

	const FIRST_DELAY = 300;

	const MAX_DELAY = 3600;

	const FORGET_AFTER = 86400;

	const MAX_ENTRIES = 500;

	public static function isWaiting( $job ) {
		$entries = self::read();
		$key     = self::key( $job );

		return isset( $entries[ $key ] ) && (int) $entries[ $key ]['retry_at'] > time();
	}

	public static function recordFailure( $job ) {
		$entries  = self::read();
		$key      = self::key( $job );
		$failures = isset( $entries[ $key ] ) ? (int) $entries[ $key ]['failures'] + 1 : 1;
		$delay    = (int) min( self::MAX_DELAY, self::FIRST_DELAY * pow( 2, min( 10, $failures - 1 ) ) );

		unset( $entries[ $key ] );
		$entries[ $key ] = [
			'failures' => $failures,
			'retry_at' => time() + $delay,
		];

		self::write( $entries );

		return $delay;
	}

	public static function clear( $job ) {
		$entries = self::read();
		$key     = self::key( $job );

		if ( isset( $entries[ $key ] ) ) {
			unset( $entries[ $key ] );
			self::write( $entries );
		}
	}

	private static function key( $job ) {
		$ateJobId = (int) Obj::prop( 'ateJobId', $job );

		return $ateJobId > 0 ? 'ate-' . $ateJobId : 'job-' . (int) Obj::prop( 'jobId', $job );
	}

	private static function read() {
		$stored = Option::getOr( self::OPTION, [] );

		if ( ! is_array( $stored ) ) {
			return [];
		}

		return array_filter(
			$stored,
			function ( $entry ) {
				return is_array( $entry ) && isset( $entry['failures'], $entry['retry_at'] );
			}
		);
	}

	private static function write( array $entries ) {
		$forgetBefore = time() - self::FORGET_AFTER;

		$entries = array_filter(
			$entries,
			function ( $entry ) use ( $forgetBefore ) {
				return (int) $entry['retry_at'] >= $forgetBefore;
			}
		);

		if ( count( $entries ) > self::MAX_ENTRIES ) {
			$entries = array_slice( $entries, -self::MAX_ENTRIES, null, true );
		}

		if ( ! $entries ) {
			Option::delete( self::OPTION );

			return;
		}

		Option::updateWithoutAutoLoad( self::OPTION, $entries );
	}
}
