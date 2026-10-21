<?php

namespace WPML\TM\ATE\PullDelivery;

use WPML\Utilities\KeyedLock;

class Pacer {

	const LOCK_NAME = 'WPML\TM\ATE\PullDelivery\Pacer';

	const LOCK_SECONDS = 5;

	private $exclusive;

	public function __construct( ?callable $exclusive = null ) {
		$this->exclusive = $exclusive ?: [ self::class, 'underPacerLock' ];
	}

	public function pace( $tabId, array $state ) {
		if ( ! is_string( $tabId ) || '' === $tabId ) {
			return [ 'isPacer' => false, 'state' => $state ];
		}

		if ( ! Modes::showsDeliveryLine( $state['mode'] ) && ! State::isSignalsReadDue( $state ) ) {
			return [ 'isPacer' => false, 'state' => $state ];
		}

		$now      = time();
		$cadence  = Cadence::collectionInterval( $state['mode'], $state['empty_runs'] );
		$lease    = Cadence::leaseLength( $cadence );
		$holdsIt  = $state['pacer_id'] === $tabId;
		$expired  = $state['pacer_lease_until'] <= $now;

		if ( $holdsIt ) {
			if ( $state['pacer_lease_until'] - $now < $cadence ) {
				$state = State::update( [ 'pacer_lease_until' => $now + $lease ] );
			}

			return [ 'isPacer' => true, 'state' => $state ];
		}

		if ( $expired ) {
			$claim = ( $this->exclusive )(
				function () use ( $tabId, $now, $lease ) {
					return State::claimPacer( $tabId, $now + $lease );
				}
			);

			if ( is_array( $claim ) && $claim['claimed'] ) {
				return [ 'isPacer' => true, 'state' => $claim['state'] ];
			}

			return [ 'isPacer' => false, 'state' => is_array( $claim ) ? $claim['state'] : $state ];
		}

		return [ 'isPacer' => false, 'state' => $state ];
	}

	private static function underPacerLock( callable $claim ) {
		global $wpdb;

		$lock = new KeyedLock( $wpdb, self::LOCK_NAME );
		$key  = $lock->create( wp_generate_uuid4(), self::LOCK_SECONDS );

		if ( ! $key ) {
			return null;
		}

		try {
			return $claim();
		} finally {
			$lock->release();
		}
	}
}
