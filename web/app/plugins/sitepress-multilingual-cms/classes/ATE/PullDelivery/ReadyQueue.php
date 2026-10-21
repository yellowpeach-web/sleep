<?php

namespace WPML\TM\ATE\PullDelivery;

use WPML\FP\Obj;
use WPML\LIB\WP\Option;
use WPML\TM\ATE\Download\Job;

class ReadyQueue {

	const OPTION = 'wpml_pull_delivery_ready_queue';

	const TTL = 600;

	public static function put( array $jobs ) {
		if ( ! $jobs ) {
			self::forget();

			return;
		}

		$kept = [];

		foreach ( $jobs as $job ) {
			$kept[] = [
				'jobId'     => (int) Obj::prop( 'jobId', $job ),
				'ateJobId'  => (int) Obj::prop( 'ateJobId', $job ),
				'ateStatus' => (int) Obj::prop( 'ateStatus', $job ),
			];
		}

		Option::updateWithoutAutoLoad(
			self::OPTION,
			[
				'written_at' => time(),
				'jobs'       => $kept,
			]
		);
	}

	public static function read() {
		$stored = Option::getOr( self::OPTION, [] );

		if (
			! is_array( $stored )
			|| empty( $stored['jobs'] )
			|| ! is_array( $stored['jobs'] )
			|| (int) Obj::propOr( 0, 'written_at', $stored ) < time() - self::TTL
		) {
			return [];
		}

		$jobs = [];

		foreach ( $stored['jobs'] as $kept ) {
			if ( ! is_array( $kept ) || empty( $kept['ateJobId'] ) ) {
				continue;
			}

			$job               = new Job();
			$job->jobId        = (int) Obj::propOr( 0, 'jobId', $kept );
			$job->ateJobId     = (int) $kept['ateJobId'];
			$job->ateStatus    = (int) Obj::propOr( 0, 'ateStatus', $kept );
			$job->isUnsolvable = false;
			$jobs[]            = $job;
		}

		return $jobs;
	}

	public static function count() {
		return count( self::read() );
	}

	public static function forget() {
		Option::delete( self::OPTION );
	}
}
