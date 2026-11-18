<?php

namespace WPML\TM\ATE\PullDelivery;

use WPML\LIB\WP\Option;

class State {

	const OPTION = 'wpml_pull_delivery_state';

	public static function defaults() {
		return [
			'loopback_ok'                      => null,
			'parked_since'                     => 0,
			'payment_parked_ate_job_ids'       => [],
			'spend_cap'                        => [],
			'insufficient_balance_job_ids'     => [],
			'insufficient_balance_ate_job_ids' => [],
			'payment_pending_job_ids'          => [],
			'payment_pending_ate_job_ids'      => [],
			'resume_attempted_for'             => '',
			'resume_checked_at'                => 0,
			'resume_attempted_balance'         => 0,
			'unsolvable_job_ids'               => [],
			'insufficient_balance_since'       => 0,
			'payment_pending_since'            => 0,
		];
	}

	public static function get() {
		$stored = Option::getOr( self::OPTION, [] );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		return array_merge( self::defaults(), array_intersect_key( $stored, self::defaults() ) );
	}

	public static function getFresh() {
		self::forgetCachedValue();

		return self::get();
	}

	public static function getKey( $key, $default = null ) {
		$state = self::get();

		return array_key_exists( $key, $state ) ? $state[ $key ] : $default;
	}

	public static function update( array $changes, ?array $state = null ) {
		$state = null === $state ? self::getFresh() : $state;
		$state = array_merge( self::defaults(), array_intersect_key( $state, self::defaults() ), array_intersect_key( $changes, self::defaults() ) );

		Option::update( self::OPTION, $state );

		return $state;
	}

	private static function forgetCachedValue() {
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( self::OPTION, 'options' );
	}

	public static function reset() {
		Option::update( self::OPTION, self::defaults() );
	}

	public static function recordLoopbackProbe( $ok ) {
		return self::update( [ 'loopback_ok' => (bool) $ok ] );
	}
}
