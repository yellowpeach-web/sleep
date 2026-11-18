<?php

namespace WPML\TM\ATE\Handshake;

use WPML\TM\ATE\Cancel\PendingCancels;

class CronTick {

	const HOOK = 'wpml_ate_handshake_tick';

	const INTERVAL_WORK_LEFT = 120;
	const INTERVAL_IDLE      = 900;

	public static function reconcile() {
		$state = Gate::withFreshInFlight( State::get() );

		if ( Gate::verdict( $state ) ) {
			$due = time() + self::interval( $state );
		} else {
			$opensAt = Gate::opensAt( $state );

			if ( $opensAt < 1 && PendingCancels::owedForRun() > 0 ) {
				$opensAt = Clock::now() + self::INTERVAL_WORK_LEFT;
			}

			if ( $opensAt < 1 ) {
				self::unschedule();

				if ( (int) $state['work_left_opened_at'] > 0 ) {
					State::update( [ 'work_left_opened_at' => 0 ] );
				}

				return false;
			}

			$due = time() + max( 1, $opensAt - Clock::now() + 1 );
		}

		$next = wp_next_scheduled( self::HOOK );

		if ( $next && $next <= $due ) {
			return (int) $next;
		}

		if ( $next ) {
			wp_clear_scheduled_hook( self::HOOK );
		}

		wp_schedule_single_event( $due, self::HOOK );

		return $due;
	}

	public static function openForWorkLeft() {
		State::update( [ 'work_left_opened_at' => Clock::now() ] );

		$due  = time() + max( 1, (int) min( self::INTERVAL_WORK_LEFT, ceil( Gate::seconds() ) ) );
		$next = wp_next_scheduled( self::HOOK );

		if ( $next && $next <= $due ) {
			return (int) $next;
		}

		if ( $next ) {
			wp_clear_scheduled_hook( self::HOOK );
		}

		wp_schedule_single_event( $due, self::HOOK );

		return $due;
	}

	public static function onTick() {
		$workLeft = (int) State::getKey( 'work_left_opened_at' ) > 0;

		if ( $workLeft ) {
			State::update( [ 'work_left_opened_at' => 0 ] );
		}

		try {
			if ( Gate::open( 'cron', $workLeft ) ) {
				( new Runner() )->run( Runner::TRIGGER_CRON );
			}
		} finally {
			self::reconcile();
		}
	}

	public static function interval( array $state ) {
		return ! empty( $state['last_run_work_left'] ) ? self::INTERVAL_WORK_LEFT : self::INTERVAL_IDLE;
	}

	public static function unschedule() {
		if ( wp_next_scheduled( self::HOOK ) ) {
			wp_clear_scheduled_hook( self::HOOK );
		}
	}
}
