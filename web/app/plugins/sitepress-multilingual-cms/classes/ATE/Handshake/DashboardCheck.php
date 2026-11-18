<?php

namespace WPML\TM\ATE\Handshake;

class DashboardCheck {

	const MIN_SECONDS_BETWEEN_RUNS = 10;

	const OUTCOME_NONE_FINISHED = 'none_finished';
	const OUTCOME_RAN_RECENTLY  = 'ran_recently';
	const OUTCOME_LOCK_BUSY     = 'lock_busy';
	const OUTCOME_RAN           = 'ran';
	const OUTCOME_SCHEDULED     = 'scheduled';
	const OUTCOME_ERROR         = 'error';

	private $runnerFactory;

	public function __construct( $runnerFactory = null ) {
		$this->runnerFactory = $runnerFactory ?: function () {
			return new Runner();
		};
	}

	public function afterSync( $jobs ) {
		if ( ! self::namesAFinishedJob( $jobs ) ) {
			return self::OUTCOME_NONE_FINISHED;
		}

		if ( (int) State::getKey( 'last_run_at' ) > Clock::now() - self::MIN_SECONDS_BETWEEN_RUNS ) {
			return self::OUTCOME_RAN_RECENTLY;
		}

		try {
			if ( Plan::phpFinishesEarly() ) {
				State::update(
					[
						'last_run_at'      => Clock::now(),
						'last_run_trigger' => Runner::TRIGGER_DASHBOARD,
					]
				);
				Runner::runAfterResponse( Runner::TRIGGER_DASHBOARD );

				return self::OUTCOME_SCHEDULED;
			}

			$runner = call_user_func( $this->runnerFactory );
			$counts = $runner->run( Runner::TRIGGER_DASHBOARD );
		} catch ( \Throwable $e ) {
			Log::write(
				'run_start',
				[
					'trigger' => Runner::TRIGGER_DASHBOARD,
					'error'   => get_class( $e ),
				]
			);

			return self::OUTCOME_ERROR;
		}

		return ! empty( $counts['lock_busy'] ) ? self::OUTCOME_LOCK_BUSY : self::OUTCOME_RAN;
	}

	public static function namesAFinishedJob( $jobs ) {
		if ( ! is_array( $jobs ) ) {
			return false;
		}

		foreach ( $jobs as $job ) {
			$status = null;

			if ( is_object( $job ) && isset( $job->ateStatus ) ) {
				$status = $job->ateStatus;
			} elseif ( is_array( $job ) && isset( $job['ateStatus'] ) ) {
				$status = $job['ateStatus'];
			}

			if ( null !== $status && in_array( (int) $status, Runner::READY_STATUSES, true ) ) {
				return true;
			}
		}

		return false;
	}
}
