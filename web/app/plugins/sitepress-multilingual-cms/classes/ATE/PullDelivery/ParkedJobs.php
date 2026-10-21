<?php

namespace WPML\TM\ATE\PullDelivery;

use WPML\TM\Jobs\JobLog;

class ParkedJobs {

	const LISTS = [
		'insufficient_balance_job_ids' => 'insufficient_balance_ate_job_ids',
		'payment_pending_job_ids'      => 'payment_pending_ate_job_ids',
	];

	public function prune( array $state ) {
		$ids = [];
		foreach ( array_keys( self::LISTS ) as $key ) {
			$ids = array_merge( $ids, $this->ids( $state, $key ) );
		}
		$ids = array_values( array_unique( $ids ) );

		if ( ! $ids ) {
			return $state;
		}

		$inFlight = $this->inFlight( $ids );
		$changes  = [];
		$dropped  = [];
		$kept     = [];

		foreach ( self::LISTS as $key => $ateKey ) {
			$listIds = $this->ids( $state, $key );
			$keep    = array_values( array_intersect( $listIds, array_keys( $inFlight ) ) );

			if ( count( $keep ) === count( $listIds ) ) {
				continue;
			}

			$dropped = array_merge( $dropped, array_values( array_diff( $listIds, $keep ) ) );
			$kept    = array_merge( $kept, $keep );

			$changes[ $key ]    = $keep;
			$changes[ $ateKey ] = array_values( array_unique( array_filter( array_map( 'intval', array_intersect_key( $inFlight, array_flip( $keep ) ) ) ) ) );
		}

		if ( ! $changes ) {
			return $state;
		}

		JobLog::add( 'pull_parked_jobs_forgotten', [ 'jobIds' => $dropped, 'kept' => $kept ] );

		return State::update( $changes );
	}

	public static function forget() {
		$state   = State::get();
		$jobIds  = [];
		$changes = [];

		foreach ( self::LISTS as $key => $ateKey ) {
			if ( $state[ $key ] || $state[ $ateKey ] ) {
				$jobIds             = array_merge( $jobIds, array_values( (array) $state[ $key ] ) );
				$changes[ $key ]    = [];
				$changes[ $ateKey ] = [];
			}
		}

		if ( ! $changes ) {
			return;
		}

		JobLog::add( 'pull_parked_jobs_forgotten', [ 'jobIds' => $jobIds, 'reason' => 'cancel_all_automatic_jobs' ] );

		State::update( $changes );
	}

	private function ids( array $state, $key ) {
		$ids = array_map( 'intval', (array) ( isset( $state[ $key ] ) ? $state[ $key ] : [] ) );

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	private function inFlight( array $jobIds ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT job.job_id, job.editor_job_id
				   FROM {$wpdb->prefix}icl_translate_job job
				   JOIN {$wpdb->prefix}icl_translation_status status ON status.rid = job.rid
				  WHERE job.job_id IN ( " . implode( ', ', array_fill( 0, count( $jobIds ), '%d' ) ) . " )
				    AND status.status IN ( %d, %d )
				    AND job.job_id = (
				        SELECT MAX( newest.job_id ) FROM {$wpdb->prefix}icl_translate_job newest WHERE newest.rid = job.rid
				    )",
				...array_merge( $jobIds, [ ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS ] )
			),
			ARRAY_A
		);

		$inFlight = [];
		foreach ( is_array( $rows ) ? $rows : [] as $row ) {
			$inFlight[ (int) $row['job_id'] ] = (int) $row['editor_job_id'];
		}

		return $inFlight;
	}
}
