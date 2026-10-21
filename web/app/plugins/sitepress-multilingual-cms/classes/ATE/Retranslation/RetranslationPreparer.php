<?php

namespace WPML\TM\ATE\Retranslation;

require_once __DIR__ . '/../../../inc/constants-since-5-0.php';

use WPML\Collect\Support\Collection;
use WPML\FP\Obj;

class RetranslationPreparer {

	private $wpdb;

	private $ateApi;

	public function __construct( \wpdb $wpdb, \WPML_TM_ATE_API $ateApi ) {
		$this->wpdb   = $wpdb;
		$this->ateApi = $ateApi;
	}


	public function delegate( array $ateJobIds ): array {
		if ( ! $ateJobIds ) {
			return [ 0, 0, [] ];
		}

		$wpdb   = $this->wpdb;
		$rowset = \wpml_collect(
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT tranlation_status.rid, tranlation_status.needs_update, tranlation_status.status,
						icl_translate_job1.job_id, icl_translate_job1.editor_job_id,
						(
							SELECT IF ( MAX(icl_translate_job2.job_id) = icl_translate_job1.job_id, 1, 0 )
							FROM {$wpdb->prefix}icl_translate_job icl_translate_job2
							WHERE icl_translate_job1.rid = icl_translate_job2.rid
						) AS is_the_most_recent_job
					FROM {$wpdb->prefix}icl_translation_status tranlation_status
					INNER JOIN {$wpdb->prefix}icl_translate_job icl_translate_job1
						ON icl_translate_job1.rid = tranlation_status.rid
					WHERE icl_translate_job1.editor = %s
					AND icl_translate_job1.editor_job_id IN (" . implode( ', ', array_fill( 0, count( $ateJobIds ), '%d' ) ) . ')',
					array_merge( [ \WPML_TM_Editors::ATE ], array_map( 'intval', $ateJobIds ) )
				)
			)
		);

		list( $jobsWhichShouldBeReFetched, $outdatedJobs ) = $rowset->partition( function($job) {
			return Obj::prop( 'is_the_most_recent_job', $job )
				&& ! Obj::prop( 'needs_update', $job )
				&& (int) Obj::prop( 'status', $job ) !== ICL_TM_ATE_UNSOLVABLE;
		} );


		if ( count( $jobsWhichShouldBeReFetched ) ) {
			$this->turnJobsIntoInProgress( $jobsWhichShouldBeReFetched );
		}

		if ( count( $outdatedJobs ) ) {
			$this->ateApi->confirm_received_job( $outdatedJobs->pluck( 'editor_job_id' )->toArray() );
		}

		return [
			count( $jobsWhichShouldBeReFetched ),
			count( $outdatedJobs ),
			$jobsWhichShouldBeReFetched->pluck( 'job_id' )->map( function ( $jobId ) {
				return (int) $jobId;
			} )->values()->toArray(),
		];
	}

	private function turnJobsIntoInProgress( Collection $jobsWhichShouldBeReFetched ) {
		$rids = array_map( 'intval', $jobsWhichShouldBeReFetched->pluck( 'rid' )->toArray() );
		$wpdb = $this->wpdb;

		$record = new \WPML\TM\Jobs\TranslationRecord( $wpdb );
		foreach ( $jobsWhichShouldBeReFetched as $job ) {
			if ( (int) Obj::prop( 'status', $job ) === ICL_TM_COMPLETE ) {
				$record->savePreviousStateForRid( (int) Obj::prop( 'rid', $job ) );
			}
		}

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_translation_status
				SET `status` = %d
				WHERE rid IN (" . implode( ',', array_fill( 0, count( $rids ), '%d' ) ) . ")
				AND `status` <> %d",
				array_merge( [ ICL_TM_IN_PROGRESS ], $rids, [ ICL_TM_ATE_UNSOLVABLE ] )
			)
		);
	}
}
