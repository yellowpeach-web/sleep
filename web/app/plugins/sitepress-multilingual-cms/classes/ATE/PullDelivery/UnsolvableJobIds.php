<?php

namespace WPML\TM\ATE\PullDelivery;

use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;
use WPML\TM\Jobs\JobLog;

class UnsolvableJobIds {

	const KEY = 'unsolvable_job_ids';

	public function prune( array $state ) {
		$listIds = self::ids( $state );

		if ( ! $listIds ) {
			return $state;
		}

		$listed = $this->listed( $listIds );
		$keep   = array_values( array_intersect( $listIds, $listed ) );

		if ( count( $keep ) === count( $listIds ) ) {
			return $state;
		}

		self::log( array_values( array_diff( $listIds, $keep ) ), $keep, 'not_listed' );

		return State::update( [ self::KEY => $keep ] );
	}

	public static function forget( array $jobIds ) {
		$jobIds  = array_values( array_unique( array_filter( array_map( 'intval', $jobIds ) ) ) );
		$listIds = self::ids( State::get() );
		$dropped = array_values( array_intersect( $listIds, $jobIds ) );

		if ( ! $dropped ) {
			return;
		}

		$keep = array_values( array_diff( $listIds, $dropped ) );

		self::log( $dropped, $keep, 'discarded' );

		State::update( [ self::KEY => $keep ] );
	}

	private static function log( array $dropped, array $kept, $reason ) {
		JobLog::maybeInitRequest();
		JobLog::createNewGroup( JobLog::GROUP_ID_DOWNLOAD_JOBS, 'ATE pull delivery: unsolvable list' );
		JobLog::add(
			'pull_unsolvable_jobs_forgotten',
			[
				'jobIds' => $dropped,
				'kept'   => $kept,
				'reason' => $reason,
			]
		);
		JobLog::finishCurrentGroup();
	}

	private static function ids( array $state ) {
		$ids = array_map( 'intval', (array) ( isset( $state[ self::KEY ] ) ? $state[ self::KEY ] : [] ) );

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	private function listed( array $jobIds ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT job.job_id
				   FROM {$wpdb->prefix}icl_translate_unsolvable_jobs error
				   JOIN {$wpdb->prefix}icl_translate_job job ON job.job_id = error.job_id
				   JOIN {$wpdb->prefix}icl_translation_status status ON status.rid = job.rid
				  WHERE job.job_id IN ( " . implode( ', ', array_fill( 0, count( $jobIds ), '%d' ) ) . " )
				    AND job.editor = 'ATE'
				    AND job.job_id = (
				        SELECT MAX( newest.job_id ) FROM {$wpdb->prefix}icl_translate_job newest WHERE newest.rid = job.rid AND newest.editor = 'ATE'
				    )
				    AND (
				        status.status = %d
				        OR (
				            (
				                error.error_type = 'SyncError'
				                OR ( error.error_type = 'DownloadError' AND error.counter >= 3 )
				                OR ( error.error_type = 'ApplyError' AND error.counter >= 1 )
				            )
				            AND status.status IN ( %d, %d )
				        )
				    )",
				...array_merge( $jobIds, [ TranslationStatus::ATE_UNSOLVABLE, ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS ] )
			),
			ARRAY_A
		);

		return array_map( 'intval', array_column( is_array( $rows ) ? $rows : [], 'job_id' ) );
	}
}
