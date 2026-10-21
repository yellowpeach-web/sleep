<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\AteResponse;
use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\BatchResult;
use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\ReleaseLedger;
use WPML\TM\ATE\Hooks\JobActions;
use WPML\TM\ATE\Release\InFlightChargedJobs;
use WPML\TM\ATE\Release\WordsLifetimeMax;
use WPML\Translation\PreviousStateServiceFactory;
use function WPML\Container\make;

class TaxonomyJobsCanceller implements \IWPML_Backend_Action, \IWPML_REST_Action, \IWPML_AJAX_Action {
	const CANCEL_BATCH_SIZE = 1000;

	public function add_hooks() {
		add_action( 'wpml_cancel_all_automatic_jobs', [ $this, 'cancelInProgressTaxonomyJobs' ], 10 );
	}

	public function cancelInProgressTaxonomyJobs( $batchResult = null ) {
		$batchResult = $batchResult instanceof BatchResult ? $batchResult : new BatchResult();

		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ts.rid, j.editor_job_id AS ate_job
					FROM {$wpdb->prefix}icl_translation_status ts
					INNER JOIN {$wpdb->prefix}icl_translations t
						ON t.translation_id = ts.translation_id
					INNER JOIN {$wpdb->prefix}icl_translate_job j
						ON j.rid = ts.rid
						AND j.job_id = (
							SELECT MAX( jj.job_id )
							FROM {$wpdb->prefix}icl_translate_job jj
							WHERE jj.rid = ts.rid
						)
					WHERE t.element_type LIKE %s
						AND ts.status = %d
						AND j.automatic = 1
						AND j.editor = %s
						AND j.editor_job_id IS NOT NULL
					ORDER BY j.job_id
					LIMIT %d",
				$wpdb->esc_like( 'tax_' ) . '%',
				ICL_TM_IN_PROGRESS,
				'ate',
				self::CANCEL_BATCH_SIZE
			)
		);

		if ( empty( $rows ) ) {
			return;
		}
		if ( self::CANCEL_BATCH_SIZE === count( $rows ) ) {
			$batchResult->markHasMore();
		}

		$ateJobIds = array_values(
			array_filter(
				array_map(
					function ( $row ) {
						return $row->ate_job;
					},
					$rows
				)
			)
		);

		$jobsHiddenInATE = $this->hideJobsInATE( $ateJobIds );
		$jobsHiddenInATE = null === $jobsHiddenInATE ? [] : $jobsHiddenInATE;
		$hiddenRids      = [];
		$localRids       = [];
		$unconfirmedJobs = 0;

		foreach ( $rows as $row ) {
			$ateJobId = (int) $row->ate_job;
			if ( $ateJobId > 0 && ! in_array( $ateJobId, $jobsHiddenInATE, true ) ) {
				$unconfirmedJobs ++;

				continue;
			}

			if ( $ateJobId > 0 ) {
				$hiddenRids[] = (int) $row->rid;
			} else {
				$localRids[] = (int) $row->rid;
			}
		}

		$previousState = PreviousStateServiceFactory::create();
		$previousState->revertInFlight( $hiddenRids, [ ICL_TM_IN_PROGRESS ], ICL_TM_ATE_CANCELLED );
		$previousState->revertInFlight( $localRids, [ ICL_TM_IN_PROGRESS ] );

		$batchResult->addProcessed( count( $hiddenRids ) + count( $localRids ) );
		if ( $unconfirmedJobs ) {
			$batchResult->addFailure( 'taxonomy_jobs_not_confirmed_by_ate' );
			$batchResult->markHasMore();
		}
	}

	private function hideJobsInATE( array $ateJobIds ) {
		$ateJobIds = array_values( array_unique( array_filter( array_map( 'intval', $ateJobIds ) ) ) );
		if ( ! $ateJobIds ) {
			return [];
		}

		try {
			$apiClient = make( \WPML_TM_ATE_API::class );
		} catch ( \Throwable $e ) {
			return null;
		}

		try {
			$response = $apiClient->hideJobs( $ateJobIds, true, \WPML_TM_ATE_API::CANCEL_REASON_CANCELLED );
		} catch ( \Throwable $e ) {
			return null;
		}

		$this->recordRelease( $response, $ateJobIds );

		return AteResponse::getConfirmedJobIds( $response );
	}

	private function recordRelease( $response, array $ateJobIds ) {
		$released    = AteResponse::getReleasedJobs( $response );
		$notReleased = AteResponse::getNotReleasedJobs( $response );
		$ledger      = ReleaseLedger::instance();
		$chargedJobs = InFlightChargedJobs::chargedAteJobIds( $ateJobIds );

		foreach ( $ateJobIds as $ateJobId ) {
			$ateJobId = (int) $ateJobId;

			if ( is_array( $released ) && isset( $released[ $ateJobId ] ) ) {
				$ledger->recordReleased(
					$ateJobId,
					(int) $released[ $ateJobId ]['words'],
					$released[ $ateJobId ]['ledger_id']
				);

				WordsLifetimeMax::releaseJob( $ateJobId, (int) $released[ $ateJobId ]['words'] );

				continue;
			}

			$reason = is_array( $notReleased ) && isset( $notReleased[ $ateJobId ] )
				? $notReleased[ $ateJobId ]
				: null;

			if (
				! in_array( $reason, JobActions::DEFINITIVE_NOT_RELEASED_REASONS, true )
				&& isset( $chargedJobs[ $ateJobId ] )
			) {
				$ledger->recordUnconfirmed( $ateJobId );
			}
		}
	}
}
