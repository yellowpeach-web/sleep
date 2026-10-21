<?php

use WPML\FP\Obj;
use WPML\TM\API\Job\Map;
use WPML\TM\Jobs\JobLog;

abstract class WPML_TM_Update_Translation_Data_Action extends WPML_Translation_Job_Helper_With_API {

	private static $jobIdCounterEnsured = false;

	private static $lastJobInsertErrno = 0;

	function get_prev_job_data( $rid ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT job_id, translated
							 FROM {$wpdb->prefix}icl_translate_job
							 WHERE rid=%d
							 	AND revision IS NULL
						     LIMIT 1",
				$rid
			),
			ARRAY_N
		);
	}

	function add_translation_job( $rid, $translator_id, array $translation_package, array $batch_options, $sendFrom = null, $addJobLogs = false, $notify = true ) {
		global $wpdb, $current_user;

		$jobId = $this->maybeRestoreATECancelledJobDueToInsufficientBalance( $rid );
		if ( $jobId ) {
			if ( $addJobLogs ) {
				JobLog::add( 'icl_translate_job record restored from the previous state with jobId `' . $jobId . '` from rid `' . $rid . '`' );
			}
			return $jobId;
		}

		$translation_status = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_translation_status WHERE rid=%d", $rid ) );

		if ( ! $translation_status ) {
			if ( $addJobLogs ) {
				JobLog::add( 'icl_translate_job record not created: rid `' . $rid . '` has no icl_translation_status row' );
			}

			return 0;
		}

		$prev_translation = $this->get_translated_field_values( $rid, $translation_package );
		if ( ! $current_user->ID ) {
			$manager_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT manager_id FROM {$wpdb->prefix}icl_translate_job WHERE rid=%d ORDER BY job_id DESC LIMIT 1",
					$rid
				)
			);
		} else {
			$manager_id = $current_user->ID;
		}

		$translate_job_insert_data = array(
			'rid'           => $rid,
			'translator_id' => $translator_id,
			'translated'    => 0,
			'manager_id'    => (int) $manager_id,
			'sent_from'     => $sendFrom,
		);

		if ( isset( $batch_options['deadline_date'] ) ) {
			$translate_job_insert_data['deadline_date'] = $batch_options['deadline_date'];
		}

		if ( ! empty( $batch_options['created_by_translate_everything'] ) ) {
			$translate_job_insert_data['created_by_translate_everything'] = 1;
		}

		if ( ! empty( $batch_options['review_mode'] ) ) {
			$translate_job_insert_data['review_mode'] = (string) $batch_options['review_mode'];
		}

		if ( isset( $translation_package['title'] ) ) {
			$translate_job_insert_data['title'] = WPML\FP\Str::truncate_bytes( $translation_package['title'], 160 );
		}

		$this->ensureJobIdCounterIsAheadOfOrphanRows();

		$inserted                 = $wpdb->insert( $wpdb->prefix . 'icl_translate_job', $translate_job_insert_data );
		self::$lastJobInsertErrno = false === $inserted ? $this->dbErrno() : 0;
		$job_id                   = false === $inserted ? 0 : (int) $wpdb->insert_id;

		if ( ! $job_id ) {
			if ( $addJobLogs ) {
				JobLog::add( 'icl_translate_job record could not be created from rid `' . $rid . '`', $translate_job_insert_data );
			}

			return 0;
		}

		Map::rememberJobIdForRid( $rid, $job_id );

		if ( $addJobLogs ) {
			JobLog::add(
				'New icl_translate_job record created with jobId `' . $job_id . '` from rid `' . $rid . '`',
				$translate_job_insert_data
			);
		}

		$this->package_helper->save_package_to_job( $translation_package, $job_id, $prev_translation, $addJobLogs );
		$this->maybeRemovedElementsBelongingToOldCompletedJobsToKeepTheTableClean( $rid );
		if ( (int) $translation_status->status !== ICL_TM_DUPLICATE ) {
			$this->fire_notification_actions( $job_id, $translation_status, $translator_id, $notify );
		}

		return $job_id;
	}

	public static function lastJobInsertErrno() {
		return self::$lastJobInsertErrno;
	}

	private function dbErrno() {
		global $wpdb;

		$dbh = $wpdb->dbh;

		return $dbh instanceof \mysqli ? (int) $dbh->errno : 0;
	}

	protected function ensureJobIdCounterIsAheadOfOrphanRows() {
		global $wpdb;

		if ( self::$jobIdCounterEnsured ) {
			return;
		}
		self::$jobIdCounterEnsured = true;

		$row = $wpdb->get_row(
			"SELECT
				(SELECT MAX(job_id) FROM {$wpdb->prefix}icl_translate)     AS max_orphan,
				(SELECT MAX(job_id) FROM {$wpdb->prefix}icl_translate_job) AS max_job"
		);

		if ( ! $row ) {
			return;
		}

		$max_orphan = (int) $row->max_orphan;
		$max_job    = (int) $row->max_job;

		if ( $max_job >= $max_orphan ) {
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				"ALTER TABLE {$wpdb->prefix}icl_translate_job AUTO_INCREMENT = %d",
				$max_orphan + 1
			)
		);

		JobLog::add(
			'Notice: icl_translate_job id counter was behind orphaned icl_translate rows; raised it to avoid reusing a job_id',
			array(
				'max_orphan_job_id' => $max_orphan,
				'max_job_id'        => $max_job,
				'new_auto_increment' => $max_orphan + 1,
			)
		);
	}

	private function maybeRemovedElementsBelongingToOldCompletedJobsToKeepTheTableClean( int $rid ) {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE t
				FROM {$wpdb->prefix}icl_translate t
				INNER JOIN (
				    SELECT job_id
				    FROM {$wpdb->prefix}icl_translate_job
				    WHERE rid = %d
				      AND job_id < (
				          SELECT MAX(job_id)
				          FROM {$wpdb->prefix}icl_translate_job
				          WHERE rid = %d AND translated = 1
				      )
				) to_delete ON t.job_id = to_delete.job_id",
				$rid,
				$rid
			)
		);
	}

	private function maybeRestoreATECancelledJobDueToInsufficientBalance( $rid ) {
		$previousStatus = \WPML\Translation\PreviousStateServiceFactory::create()->getByRid( $rid );
		if ( $previousStatus && (int) $previousStatus['status'] === ICL_TM_ATE_CANCELLED ) {
			return Map::fromRid( $rid );
		}

		return null;
	}

	abstract protected function populate_prev_translation( $prev_id, array $package );

	protected function get_translated_field_values( $rid, array $package ) {
		global $wpdb;

		$prev_translations = $this->populate_prev_translation( $rid, $package );

		if ( ! $prev_translations ) {
			return array();
		}

		list( $prev_job_id, $prev_job_translated ) = $this->get_prev_job_data( $rid );

		if ( ! is_null( $prev_job_id ) ) {
			$last_rev         = $wpdb->get_var( $wpdb->prepare(
				"
				SELECT MAX(revision)
				FROM {$wpdb->prefix}icl_translate_job
				WHERE rid=%d
					AND ( revision IS NOT NULL OR translated = 1 )
			",
				$rid
			) );
			$wpdb->update( $wpdb->prefix . 'icl_translate_job', array( 'revision' => $last_rev + 1 ), array( 'job_id' => $prev_job_id ) );
		}

		return $prev_translations;
	}

	protected function fire_notification_actions( $job_id, $translation_status, $translator_id, $notify = true ) {
		if ( ! $job_id ) {
			return;
		}
		if ( 'local' !== $translation_status->translation_service ) {
			return;
		}

		$job = wpml_tm_load_job_factory()->get_translation_job( $job_id, false, 0, true );
		if ( ! $job ) {
			return;
		}

		if ( $notify && ICL_TM_NOTIFICATION_IMMEDIATELY === (int) $this->get_tm_setting( array( 'notification', 'new-job' ) ) ) {
			if ( empty( $translator_id ) ) {
				do_action( 'wpml_tm_new_job_notification', $job );
			} else {
				do_action( 'wpml_tm_assign_job_notification', $job, $translator_id );
			}
		}

		do_action( 'wpml_added_local_translation_job', $job_id );
	}
}
