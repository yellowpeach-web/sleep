<?php

namespace WPML\TM\Upgrade\Commands;

class RepairReviewedJobRows implements \IWPML_Upgrade_Command {

	const RESULT_OPTION = 'wpml_tm_reviewed_job_rows_repaired';

	private $result = false;

	public function run_admin() {
		global $wpdb;

		$repaired = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_translate_job j
				 INNER JOIN {$wpdb->prefix}icl_translation_status s
				         ON s.rid = j.rid
				 INNER JOIN (
				     SELECT rid, MAX( job_id ) AS job_id
				     FROM {$wpdb->prefix}icl_translate_job
				     GROUP BY rid
				 ) latest
				         ON latest.rid = j.rid AND latest.job_id = j.job_id
				 SET j.translated = 1
				 WHERE j.translated = 0
				   AND s.status = %d
				   AND s.review_status = %s",
				ICL_TM_COMPLETE,
				'ACCEPTED'
			)
		);

		if ( false === $repaired ) {
			return false;
		}

		$this->result = (int) $repaired;

		update_option(
			self::RESULT_OPTION,
			[
				'count' => $this->result,
				'at'    => gmdate( 'Y-m-d H:i:s' ),
			],
			false
		);

		do_action( 'wpml_tm_reviewed_job_rows_repaired', $this->result );

		return true;
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
