<?php

class WPML_TP_Sync_Jobs {

	private $jobs_status_sync;

	private $jobs_revision_sync;

	private $update_job;

	public function __construct(
		WPML_TM_Sync_Jobs_Status $jobs_status_sync,
		WPML_TM_Sync_Jobs_Revision $jobs_revision_sync,
		WPML_TP_Sync_Update_Job $update_job
	) {
		$this->jobs_status_sync   = $jobs_status_sync;
		$this->jobs_revision_sync = $jobs_revision_sync;
		$this->update_job         = $update_job;
	}

	public function sync( ?array $tp_ids = null ) {
		$jobs = $this->jobs_status_sync->sync( $tp_ids );

		if ( null === $tp_ids ) {
			$jobs = $jobs->append( $this->jobs_revision_sync->sync() );
		}

		return new WPML_TM_Jobs_Collection(
			$jobs
			->filter_by_status( array( ICL_TM_IN_PROGRESS, ICL_TM_WAITING_FOR_TRANSLATOR ), true )
			->map( array( $this->update_job, 'update_state' ) )
		);
	}

	public function get_in_flight_tp_ids( $after_tp_id = 0, $limit = 0 ) {
		return $this->jobs_status_sync->get_in_flight_tp_ids( $after_tp_id, $limit );
	}

	public function count_in_flight_tp_ids( $after_tp_id = 0 ) {
		return $this->jobs_status_sync->count_in_flight_tp_ids( $after_tp_id );
	}

	public function sync_revised() {
		$revised = new WPML_TM_Jobs_Collection( $this->jobs_revision_sync->sync() );

		return new WPML_TM_Jobs_Collection(
			$revised
			->filter_by_status( array( ICL_TM_IN_PROGRESS, ICL_TM_WAITING_FOR_TRANSLATOR ), true )
			->map( array( $this->update_job, 'update_state' ) )
		);
	}
}
