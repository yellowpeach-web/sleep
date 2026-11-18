<?php

namespace WPML\TM\ATE\Retry;

use WPML\TM\ATE\Log\Entry;
use WPML\TM\ATE\Log\Storage;
use WPML\TM\ATE\Receive\JobKind;
use WPML\TM\ATE\TranslateEverything\TaxonomyTermJobDispatcher;
use WPML\TM\Jobs\JobLog;
use function WPML\Container\make;

class TermBundles {

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function partition( array $jobIds ): array {
		$jobIds = array_values( array_unique( array_map( 'intval', $jobIds ) ) );
		if ( ! $jobIds ) {
			return [ [], [] ];
		}

		$bundles = [];
		$others  = [];
		foreach ( $jobIds as $jobId ) {
			if ( JobKind::isTermBundle( $jobId ) ) {
				$bundles[] = $jobId;
			} else {
				$others[] = $jobId;
			}
		}

		return [ $bundles, $others ];
	}

	public function resend( array $jobIds ): array {
		$outcomes = [];

		foreach ( array_map( 'intval', $jobIds ) as $jobId ) {
			$dispatcher         = make( TaxonomyTermJobDispatcher::class, [ ':sentFromRetry' => true ] );
			$outcome            = $dispatcher->resendTermBundle( $jobId );
			$outcomes[ $jobId ] = $outcome;

			if ( TaxonomyTermJobDispatcher::RESEND_PARKED === $outcome ) {
				$this->logRound( $jobId );
			}
		}

		return $outcomes;
	}

	private function logRound( int $jobId ): void {
		$wpdb = $this->wpdb;

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT s.ate_comm_retry_count
				 FROM {$wpdb->prefix}icl_translation_status s
				 INNER JOIN {$wpdb->prefix}icl_translate_job j ON j.rid = s.rid
				 WHERE j.job_id = %d",
				$jobId
			)
		);

		if ( $count ) {
			Storage::add( Entry::retryJob( $jobId, [ 'retry_count' => $count, 'kind' => 'term_bundle' ] ) );
		}

		JobLog::add( 'term_bundle_retry_round_counted', [ 'job_id' => $jobId, 'retry_count' => $count ] );
	}
}
