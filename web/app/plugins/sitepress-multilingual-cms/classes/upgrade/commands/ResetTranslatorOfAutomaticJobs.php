<?php

namespace WPML\TM\Upgrade\Commands;

class ResetTranslatorOfAutomaticJobs implements \IWPML_Upgrade_Command {
	private $result = false;

	public function run_admin() {
		global $wpdb;

		$automatic_column_exists = $wpdb->get_var(
			"SHOW COLUMNS
			FROM `{$wpdb->prefix}icl_translate_job`
			LIKE 'automatic'"
		);

		if ( ! $automatic_column_exists ) {
			return true;
		}


		$rowsToUpdate = $wpdb->get_results( "
		SELECT jobs.job_id, jobs.rid
		FROM {$wpdb->prefix}icl_translate_job jobs
		INNER JOIN (
			SELECT rid, MAX(job_id) AS job_id
			FROM {$wpdb->prefix}icl_translate_job
			GROUP BY rid
		) latest_job ON latest_job.job_id = jobs.job_id
		WHERE jobs.automatic = 1
		" );

		if ( count( $rowsToUpdate ) ) {
			$rids = array_map( 'intval', array_column( $rowsToUpdate, 'rid' ) );
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}icl_translation_status translation_status
					SET translation_status.translator_id = 0
					WHERE translation_status.rid IN (" . implode( ', ', array_fill( 0, count( $rids ), '%d' ) ) . ')',
					$rids
				)
			);

			$jobIds = array_map( 'intval', array_column( $rowsToUpdate, 'job_id' ) );
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}icl_translate_job
					SET translator_id = 0
					WHERE job_id IN (" . implode( ', ', array_fill( 0, count( $jobIds ), '%d' ) ) . ')',
					$jobIds
				)
			);
		}

		$this->result = true;

		return $this->result;
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return null;
	}

	public function get_results() {
		return $this->result;
	}

}
