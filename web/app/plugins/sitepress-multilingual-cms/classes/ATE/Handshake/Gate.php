<?php

namespace WPML\TM\ATE\Handshake;

use WPML\TM\ATE\Cancel\PendingCancels;
use WPML\TM\ATE\PullDelivery\PendingJobs;

class Gate {

	const DEFAULT_MINUTES = 15;

	const IN_FLIGHT_MAX_AGE = 60;

	private static $summary = null;

	public static function open( $reader = 'cron', $workLeft = false ) {
		$state   = self::withFreshInFlight( State::get() );
		$owed     = 'cron' === $reader ? PendingCancels::owedForRun() : 0;
		$verdict  = self::verdict( $state ) || ( $workLeft && (int) $state['in_flight'] > 0 ) || $owed > 0;
		$openedAt = self::opensAt( $state );

		Log::write(
			'gate',
			[
				'reader'                 => (string) $reader,
				'in_flight'              => (int) $state['in_flight'],
				'sent_uncollected_since' => (int) $state['sent_uncollected_since'],
				'last_wake_received_at'  => (int) $state['last_wake_received_at'],
				'last_admin_load_at'     => (int) $state['last_admin_load_at'],
				'self_poll_after_s'      => self::seconds(),
				'opens_at'               => $openedAt,
				'work_left'              => (bool) $workLeft,
				'owed_for_run'           => $owed,
				'now'                    => Clock::now(),
				'open'                   => $verdict,
			]
		);

		return $verdict;
	}

	public static function verdict( array $state ) {
		$opensAt = self::opensAt( $state );

		return $opensAt > 0 && $opensAt < Clock::now();
	}

	public static function opensAt( array $state ) {
		$inFlight = isset( $state['in_flight'] ) ? (int) $state['in_flight'] : 0;
		$sent     = isset( $state['sent_uncollected_since'] ) ? (int) $state['sent_uncollected_since'] : 0;
		$wake     = isset( $state['last_wake_received_at'] ) ? (int) $state['last_wake_received_at'] : 0;

		if ( $inFlight < 1 || $sent < 1 ) {
			return 0;
		}

		return (int) ceil( max( $wake, $sent ) + self::seconds() );
	}

	public static function seconds() {
		$minutes = apply_filters( 'wpml_ate_handshake_self_poll_after_minutes', self::DEFAULT_MINUTES );

		return 60 * ( is_numeric( $minutes ) && $minutes >= 0 ? (float) $minutes : self::DEFAULT_MINUTES );
	}

	public static function withFreshInFlight( array $state ) {
		if ( (int) $state['sent_uncollected_since'] < 1 ) {
			return $state;
		}

		if ( (int) $state['in_flight_at'] >= Clock::now() - self::IN_FLIGHT_MAX_AGE ) {
			return $state;
		}

		$summary = self::$summary ? call_user_func( self::$summary ) : ( new Marks() )->withUnconfirmed( ( new PendingJobs() )->summary() );

		return State::recordInFlightSummary( $summary );
	}

	public static function readInFlightFromForTests( $summary ) {
		self::$summary = $summary;
	}
}
