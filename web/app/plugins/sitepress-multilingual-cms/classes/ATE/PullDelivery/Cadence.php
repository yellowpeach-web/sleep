<?php

namespace WPML\TM\ATE\PullDelivery;

class Cadence {

	const ACTIVE_FLOOR = 120;

	const PARKED_GRACE = 300;

	const WAKE_FLOOR = 5;

	const COLLECT_MAX_JOBS = 50;

	const COLLECT_MAX_SECONDS = 20;

	const EXECUTION_HEADROOM = 10;

	public static function activeFloor() {
		return self::filtered( 'wpml_pull_delivery_active_floor', self::ACTIVE_FLOOR );
	}

	public static function parkedGrace() {
		return self::filtered( 'wpml_pull_delivery_parked_grace', self::PARKED_GRACE );
	}

	public static function wakeFloor() {
		return self::filtered( 'wpml_pull_delivery_wake_floor', self::WAKE_FLOOR );
	}

	public static function maxJobsPerRun() {
		return max( 1, self::filtered( 'wpml_pull_delivery_max_jobs_per_run', self::COLLECT_MAX_JOBS ) );
	}

	public static function maxSecondsPerRun() {
		return max( 1, self::filtered( 'wpml_pull_delivery_max_seconds_per_run', self::COLLECT_MAX_SECONDS ) );
	}

	public static function collectBudgetSeconds() {
		$budget = self::maxSecondsPerRun();
		$limit  = (int) ini_get( 'max_execution_time' );

		if ( $limit > 0 ) {
			$budget = min( $budget, $limit - self::EXECUTION_HEADROOM );
		}

		return max( 1, $budget );
	}

	private static function filtered( $filter, $default ) {
		$value = apply_filters( $filter, $default );

		return is_numeric( $value ) ? (int) $value : (int) $default;
	}
}
