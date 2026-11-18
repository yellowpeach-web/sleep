<?php

namespace WPML\Upgrade\Commands;

require_once __DIR__ . '/../../../inc/constants-since-5-0.php';

use WPML\Core\Component\Translation\Domain\Entity\JobError;
use WPML\TM\Jobs\JobLog;

class ReopenGivenUpDownloads implements \IWPML_Upgrade_Command {

	const PAGE_SIZE = 500;

	private $wpdb;

	private $reopened = 0;

	public function __construct( array $args ) {
		$this->wpdb = $args[0];
	}

	public function run() {
		if ( ! class_exists( JobLog::class ) ) {
			return $this->runPass();
		}

		return JobLog::runInGroup(
			JobLog::GROUP_ID_UPGRADE_COMMANDS,
			'Upgrade: put the deliveries given up on a download error back in flight',
			function () {
				return $this->runPass();
			}
		);
	}

	private function runPass() {
		$wpdb           = $this->wpdb;
		$this->reopened = 0;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT job.job_id AS job_id, job.rid AS rid, job.editor_job_id AS ate_job_id
				   FROM {$wpdb->prefix}icl_translate_unsolvable_jobs error
				  INNER JOIN {$wpdb->prefix}icl_translate_job job ON job.job_id = error.job_id
				  INNER JOIN {$wpdb->prefix}icl_translation_status status ON status.rid = job.rid
				  WHERE error.error_type = %s
				    AND status.status = %d
				    AND job.editor = %s
				    AND job.editor_job_id > 0
				    AND NOT EXISTS ( SELECT 1 FROM {$wpdb->prefix}icl_translate_job newer WHERE newer.rid = job.rid AND newer.job_id > job.job_id )
				  ORDER BY job.job_id
				  LIMIT %d",
				JobError::TYPE_DOWNLOAD,
				ICL_TM_ATE_UNSOLVABLE,
				\WPML_TM_Editors::ATE,
				self::PAGE_SIZE
			)
		);

		if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
			return false;
		}

		if ( ! $rows ) {
			return true;
		}

		$jobIds = array_map( 'intval', array_column( $rows, 'job_id' ) );
		$rids   = array_map( 'intval', array_column( $rows, 'rid' ) );

		$written = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_translation_status SET status = %d
				  WHERE status = %d AND rid IN ( " . implode( ',', array_fill( 0, count( $rids ), '%d' ) ) . ' )',
				array_merge( [ ICL_TM_IN_PROGRESS, ICL_TM_ATE_UNSOLVABLE ], $rids )
			)
		);
		if ( false === $written ) {
			return false;
		}

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}icl_translate_unsolvable_jobs
				  WHERE error_type = %s AND job_id IN ( " . implode( ',', array_fill( 0, count( $jobIds ), '%d' ) ) . ' )',
				array_merge( [ JobError::TYPE_DOWNLOAD ], $jobIds )
			)
		);
		if ( false === $deleted ) {
			return false;
		}

		$this->reopened = (int) $written;

		if ( class_exists( JobLog::class ) ) {
			JobLog::add(
				'given_up_downloads_reopened',
				[
					'job_ids'     => $jobIds,
					'ate_job_ids' => array_map( 'intval', array_column( $rows, 'ate_job_id' ) ),
				]
			);
		}

		return count( $rows ) < self::PAGE_SIZE;
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return $this->run();
	}

	public function get_results() {
		return $this->reopened;
	}
}
