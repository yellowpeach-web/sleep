<?php

namespace WPML\TM\ATE\Retranslation;

use WPML\TM\Jobs\JobLog;
use WPML\WP\OptionManager;

class Scheduler {

	const STATE_OPTION = 'wpml_ate_retranslation_state';

	const INTERVAL = 60 * 2;

	const UNDELIVERED_CHECK_INTERVAL = 60 * 60;

	public function shouldRun(): bool {
		$state    = $this->readState();
		$lastCall = (int) ( $state['last_call'] ?? 0 );
		$now      = time();

		if ( ! $lastCall ) {
			return $this->undeliveredCheckIsDue( $state, $now );
		}

		$elapsed = $now - $lastCall;
		$passed  = $elapsed > self::INTERVAL;

		JobLog::addRetranslationEvent( 'retranslation_scheduler_should_run', [
			'last_call'                    => $lastCall,
			'now'                          => $now,
			'elapsed'                      => $elapsed,
			'interval'                     => self::INTERVAL,
			'interval_passed'              => $passed,
			'shouldCheckForRetranslation'  => $passed,
		] );

		return $passed;
	}

	private function undeliveredCheckIsDue( array $state, int $now ): bool {
		$lastCheck = (int) ( $state['last_undelivered_check'] ?? 0 );

		if ( $now - $lastCheck <= self::UNDELIVERED_CHECK_INTERVAL ) {
			return false;
		}

		$this->mutateState( function ( array $state ) use ( $now ) {
			$state['last_undelivered_check'] = $now;

			return $state;
		} );

		JobLog::addRetranslationEvent( 'retranslation_scheduler_undelivered_check', [
			'last_check' => $lastCheck ?: null,
			'now'        => $now,
			'interval'   => self::UNDELIVERED_CHECK_INTERVAL,
		] );

		return true;
	}

	public function scheduleNextRun() {
		$previous = 0;
		$now      = time();

		$this->mutateState( function ( array $state ) use ( $now, &$previous ) {
			$previous           = (int) ( $state['last_call'] ?? 0 );
			$state['last_call'] = $now;

			return $state;
		} );

		JobLog::addRetranslationEvent( 'retranslation_scheduler_next_run_scheduled', [
			'previous_last_call' => $previous ?: null,
			'last_call'          => $now,
			'interval'           => self::INTERVAL,
		] );
	}

	public function disable() {
		$this->mutateState( function ( array $state ) {
			unset( $state['last_call'] );

			return $state;
		} );

		JobLog::addRetranslationEvent( 'retranslation_scheduler_disabled' );
	}

	public function lastCall() {
		$lastCall = (int) ( $this->readState()['last_call'] ?? 0 );

		return $lastCall ?: null;
	}

	private function readState(): array {
		$state = get_option( self::STATE_OPTION, [] );

		return is_array( $state ) ? $state : [];
	}

	private function mutateState( callable $updater ) {
		( new OptionManager() )->mutateRaw(
			self::STATE_OPTION,
			function ( $current ) use ( $updater ) {
				return $updater( is_array( $current ) ? $current : [] );
			},
			false
		);
	}
}
