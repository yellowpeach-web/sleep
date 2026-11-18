<?php

namespace WPML\TM\ATE\Sync;

use WPML\TM\ATE\Cancel\PendingCancels;
use WPML\TM\ATE\Handshake\Decline;
use WPML\TM\ATE\Jobs;
use WPML\TM\Jobs\JobLog;
use WPML\Translation\CancelJobsServiceFactory;

class OrphanedJobs {

	const BATCH  = 50;
	const LOG_ID = 'ate_sync_orphaned_jobs_closed';

	private $jobs;

	private $cancel;

	private $decline;

	public function __construct( $jobs = null, $cancel = null, $decline = null ) {
		$this->jobs    = $jobs;
		$this->cancel  = $cancel;
		$this->decline = $decline;
	}

	public function close() {
		try {
			$jobs = $this->jobs ?: new Jobs();
			$rows = $jobs->getOrphanedPendingJobs( self::BATCH );

			if ( ! $rows ) {
				return 0;
			}

			$jobIds    = [];
			$ateJobIds = [];
			foreach ( $rows as $row ) {
				$jobIds[]    = (int) $row->job_id;
				$ateJobIds[] = (int) $row->editor_job_id;
			}

			JobLog::maybeInitRequest();
			JobLog::createNewGroup( JobLog::GROUP_ID_SYNC_JOBS, 'ATE jobs whose original is gone, closed' );
			JobLog::add( self::LOG_ID, [ 'job_ids' => $jobIds, 'ate_job_ids' => $ateJobIds ] );

			$cancel = $this->cancel ?: function ( array $ids ) {
				return CancelJobsServiceFactory::create()->cancelJobs( $ids, false, \WPML\TM\ATE\Cancel\CancelSource::ORPHAN_CLEANUP );
			};
			$result = call_user_func( $cancel, $jobIds );

			$cancelled = is_array( $result ) && isset( $result['cancelledJobIds'] ) ? array_map( 'intval', (array) $result['cancelledJobIds'] ) : [];

			$this->declineAtAte( $rows, $cancelled );

			JobLog::finishCurrentGroup();

			return count( $cancelled );
		} catch ( \Throwable $e ) {
			JobLog::addError( self::LOG_ID . '_failed', [ 'message' => $e->getMessage() ] );
			JobLog::finishCurrentGroup();

			return 0;
		}
	}

	private function declineAtAte( array $rows, array $cancelled ) {
		$byAteJobId = [];
		foreach ( $rows as $row ) {
			$ateJobId = (int) $row->editor_job_id;
			if ( $ateJobId > 0 && in_array( (int) $row->job_id, $cancelled, true ) ) {
				$byAteJobId[ $ateJobId ] = (int) $row->job_id;
			}
		}

		if ( ! $byAteJobId ) {
			return;
		}

		$decline = $this->decline ?: function ( array $wpmlJobIdByAteJobId ) {
			return PendingCancels::declineOrOwe( $wpmlJobIdByAteJobId, Decline::POST_DELETED, 0 );
		};

		$result = call_user_func( $decline, $byAteJobId );

		JobLog::add( self::LOG_ID . '_declined', [
			'ate_job_ids' => array_keys( $byAteJobId ),
			'declined'    => is_array( $result ) && isset( $result['declined'] ) ? $result['declined'] : [],
			'refused'     => is_array( $result ) && isset( $result['refused'] ) ? $result['refused'] : [],
			'owed'        => is_array( $result ) && isset( $result['owed'] ) ? $result['owed'] : [],
		] );
	}
}
