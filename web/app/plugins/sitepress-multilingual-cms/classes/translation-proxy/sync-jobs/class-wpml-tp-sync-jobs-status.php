<?php

class WPML_TM_Sync_Jobs_Status {

	const IN_FLIGHT_WHERE = "status IN (%d,%d) AND translation_service != 'local' AND tp_id > %d";

	private $jobs_repository;

	private $tp_api;

	public function __construct( WPML_TM_Jobs_Repository $jobs_repository, WPML_TP_Jobs_API $tp_api ) {
		$this->jobs_repository = $jobs_repository;
		$this->tp_api          = $tp_api;
	}

	public function sync( ?array $tp_ids = null ) {
		$params = array(
			'status' => array( ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS ),
			'scope'  => WPML_TM_Jobs_Search_Params::SCOPE_REMOTE,
		);

		if ( null !== $tp_ids ) {
			$params['tp_id'] = $tp_ids;
		}

		return $this->update_tp_state_of_jobs(
			$this->jobs_repository->get_collection( new WPML_TM_Jobs_Search_Params( $params ) )
		);
	}

	private function update_tp_state_of_jobs( WPML_TM_Jobs_Collection $jobs ) {
		$tp_ids = $this->extract_tp_id_from_jobs( $jobs );

		if ( $tp_ids ) {
			$tp_statuses = $this->tp_api->get_jobs_statuses( $tp_ids );
			foreach ( $tp_statuses as $job_status ) {
				$job = $jobs->get_by_tp_id( $job_status->get_tp_id() );
				if ( $job ) {
					$previous_status = $job->get_status();
					$new_status      = WPML_TP_Job_States::map_tp_state_to_local( $job_status->get_status() );

					$job->set_status( $new_status );
					$job->set_ts_status( $job_status->get_ts_status() );

					if ( $previous_status !== $new_status && \WPML\TM\Jobs\JobLog::canLog() ) {
						\WPML\TM\Jobs\JobLog::add(
							'tp_job_status_changed',
							array(
								'tp_id' => $job_status->get_tp_id(),
								'from'  => $previous_status,
								'to'    => $new_status,
							)
						);
					}

					if ( WPML_TP_Job_States::CANCELLED === $job_status->get_status() ) {
						\WPML\TM\Jobs\JobLog::add(
							'tp_job_cancelled_by_service',
							array( 'tp_id' => $job_status->get_tp_id() )
						);
						do_action( 'wpml_tm_canceled_job_notification', $job );
					}
				}
			}
		}

		return $jobs;
	}

	public function get_in_flight_tp_ids( $after_tp_id = 0, $limit = 0 ) {
		$wpdb = $GLOBALS['wpdb'];

		$sql = $wpdb->prepare(
			'SELECT tp_id FROM ' . $wpdb->prefix . 'icl_translation_status'
			. " WHERE status IN (%d,%d) AND translation_service != 'local' AND tp_id > %d"
			. ' ORDER BY tp_id ASC',
			ICL_TM_WAITING_FOR_TRANSLATOR,
			ICL_TM_IN_PROGRESS,
			(int) $after_tp_id
		);

		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d', (int) $limit );
		}

		$ids = $wpdb->get_col( $sql );

		return array_values( array_map( 'intval', (array) $ids ) );
	}

	public function count_in_flight_tp_ids( $after_tp_id = 0 ) {
		$wpdb = $GLOBALS['wpdb'];

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'icl_translation_status'
				. " WHERE status IN (%d,%d) AND translation_service != 'local' AND tp_id > %d",
				ICL_TM_WAITING_FOR_TRANSLATOR,
				ICL_TM_IN_PROGRESS,
				(int) $after_tp_id
			)
		);

		return (int) $count;
	}

	private function extract_tp_id_from_jobs( WPML_TM_Jobs_Collection $jobs ) {
		$tp_ids = array();
		foreach ( $jobs as $job ) {
			$tp_ids[] = $job->get_tp_id();
		}

		return $tp_ids;
	}

}
