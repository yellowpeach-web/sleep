<?php

use WPML\TM\API\Job\Map;
use WPML\TM\ATE\JobRecords;

class WPML_TM_ATE_Job_Data_Fallback implements IWPML_Action {
	private $ate_api;

	public function __construct( WPML_TM_ATE_API $ate_api ) {
		$this->ate_api = $ate_api;
	}


	public function add_hooks() {
		add_filter( 'wpml_tm_ate_job_data_fallback', array( $this, 'get_data_from_api' ), 10, 2 );
	}

	public function get_data_from_api( array $data, $wpml_job_id ) {
		$wpml_job_id = (int) $wpml_job_id;

		$rid = (int) Map::fromJobId( $wpml_job_id );
		if ( $rid <= 0 ) {
			return $data;
		}

		$newest = (int) Map::fromRid( $rid );
		if ( $newest && $newest !== $wpml_job_id ) {
			return $data;
		}

		$response = $this->ate_api->get_jobs_by_wpml_ids( array( $rid ) );
		if ( ! $response || is_wp_error( $response ) ) {
			return $data;
		}

		if ( ! isset( $response->{$rid}->ate_job_id ) ) {
			return $data;
		}

		$entry = $response->{$rid};

		if ( isset( $entry->wpml_job_id ) && (int) $entry->wpml_job_id && (int) $entry->wpml_job_id !== $wpml_job_id ) {
			return $data;
		}

		$holder = $this->jobHoldingAteRecord( (int) $entry->ate_job_id );
		if ( $holder && $holder !== $wpml_job_id ) {
			return $data;
		}

		$ate_status = isset( $entry->status_id ) ? $entry->status_id : ( isset( $entry->status ) ? $entry->status : null );
		if ( null !== $ate_status && in_array( (int) $ate_status, WPML_TM_ATE_Jobs_Actions::ATE_RECORD_SPENT_STATUSES, true ) ) {
			return $data;
		}

		return array( JobRecords::FIELD_ATE_JOB_ID => $entry->ate_job_id );
	}

	private function jobHoldingAteRecord( $ate_job_id ) {
		global $wpdb;

		if ( $ate_job_id <= 0 ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT job_id FROM {$wpdb->prefix}icl_translate_job WHERE editor_job_id = %d LIMIT 1",
				$ate_job_id
			)
		);
	}
}
