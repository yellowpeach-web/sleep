<?php

class WPML_TP_Apply_Translations {

	const FATAL_ERROR_TYPES = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );

	private static $job_in_flight = null;

	private static $fatal_watch_registered = false;

	private $last_failures = array();
	private $jobs_repository;

	private $apply_single_job;

	private $tp_sync;

	public function __construct(
		WPML_TM_Jobs_Repository $jobs_repository,
		WPML_TP_Apply_Single_Job $apply_single_job,
		WPML_TP_Sync_Jobs $tp_sync
	) {
		$this->jobs_repository  = $jobs_repository;
		$this->apply_single_job = $apply_single_job;
		$this->tp_sync          = $tp_sync;
	}

	public function apply( array $params ) {
		$jobs = $this->get_jobs( $params );

		$deadline = microtime( true ) + \WPML\TM\TranslationProxy\SendTuning::receiveTimeBudgetSeconds();

		if ( microtime( true ) < $deadline && $this->has_in_progress_jobs( $jobs ) ) {
			$jobs = $this->sync_jobs( $jobs );
		}
		$cancelled_jobs = $jobs->filter_by_status( ICL_TM_NOT_TRANSLATED );

		$this->last_failures = array();
		$applied             = array();
		$cap                 = \WPML\TM\TranslationProxy\SendTuning::applyFailureCap();

		$job_started = false;

		foreach ( $this->failed_jobs_last( $jobs->filter_by_status( [ ICL_TM_TRANSLATION_READY_TO_DOWNLOAD, ICL_TM_COMPLETE ] ) ) as $job ) {
			$tp_id    = (int) $job->get_tp_id();
			$previous = \WPML\TM\TranslationProxy\ApplyFailureStore::get( $tp_id );

			if ( $job_started && microtime( true ) >= $deadline ) {
				$this->last_failures[] = $this->failure_entry(
					$job,
					__( 'Not attempted yet: the request ran out of time.', 'sitepress' ),
					'deferred',
					$previous ? (int) $previous['count'] : 0
				);
				continue;
			}

			if ( $previous && (int) $previous['count'] >= $cap ) {
				$cycles      = isset( $previous['cycles'] ) ? (int) $previous['cycles'] : 0;
				$last        = isset( $previous['last'] ) ? (int) $previous['last'] : 0;
				$retry_after = \WPML\TM\TranslationProxy\SendTuning::applyFailureRetryAfterSeconds( $cycles );
				$retry_at    = $last + $retry_after;

				if ( ! $last || time() >= $retry_at ) {
					$previous = array(
						'count'  => 0,
						'last'   => $last,
						'reason' => (string) $previous['reason'],
						'cycles' => $cycles + 1,
					);
					\WPML\TM\TranslationProxy\ApplyFailureStore::put( $tp_id, $previous );
				} else {
					\WPML\TM\Jobs\JobLog::addError(
						'tp_apply_job_skipped_at_cap',
						array(
							'job_id'   => $job->get_id(),
							'tp_id'    => $tp_id,
							'attempts' => (int) $previous['count'],
							'reason'   => (string) $previous['reason'],
							'retry_at' => $retry_at,
						)
					);
					$this->last_failures[] = $this->failure_entry( $job, (string) $previous['reason'], 'skipped', (int) $previous['count'] ) + array( 'retryAt' => $retry_at );
					continue;
				}
			}

			$job_started = true;

			$attempts = ( $previous ? (int) $previous['count'] : 0 ) + 1;
			$cycles = $previous && isset( $previous['cycles'] ) ? (int) $previous['cycles'] : 0;

			$attempt_record = array(
				'count'  => $attempts,
				'last'   => time(),
				'reason' => self::unfinished_save_reason(),
				'cycles' => $cycles,
			);
			\WPML\TM\TranslationProxy\ApplyFailureStore::put( $tp_id, $attempt_record );
			$this->watch_for_fatal( $tp_id, $attempt_record );

			try {
				$applied[] = $this->apply_single_job->apply( $job );

				\WPML\TM\TranslationProxy\ApplyFailureStore::forget( $tp_id );
			} catch ( \Throwable $e ) {
				$reason = $this->sanitize_failure_reason( $e );

				\WPML\TM\TranslationProxy\ApplyFailureStore::put(
					$tp_id,
					array(
						'count'  => $attempts,
						'last'   => time(),
						'reason' => $reason,
						'cycles' => $cycles,
					)
				);

				$this->last_failures[] = $this->failure_entry( $job, $reason, 'failed', $attempts );

				\WPML\TM\Jobs\JobLog::addError(
					'tp_apply_job_failed',
					array(
						'job_id'   => $job->get_id(),
						'tp_id'    => $tp_id,
						'attempts' => $attempts,
						'error'    => $e->getMessage(),
					)
				);
			} finally {
				self::$job_in_flight = null;
			}
		}

		\WPML\TM\TranslationProxy\ApplyFailureStore::purgeStale();

		$downloaded_jobs = new WPML_TM_Jobs_Collection( $applied );

		return $downloaded_jobs->append( $cancelled_jobs );
	}

	public function getLastFailures() {
		return $this->last_failures;
	}

	private function failed_jobs_last( WPML_TM_Jobs_Collection $jobs ) {
		$clean  = array();
		$failed = array();

		foreach ( $jobs as $job ) {
			if ( \WPML\TM\TranslationProxy\ApplyFailureStore::get( (int) $job->get_tp_id() ) ) {
				$failed[] = $job;
			} else {
				$clean[] = $job;
			}
		}

		return array_merge( $clean, $failed );
	}

	private function failure_entry( WPML_TM_Job_Entity $job, $reason, $kind, $attempts ) {
		return array(
			'id'       => $job->get_id(),
			'type'     => $job->get_type(),
			'tp_id'    => (int) $job->get_tp_id(),
			'title'    => (string) $job->get_title(),
			'language' => (string) $job->get_target_language(),
			'reason'   => $reason,
			'kind'     => $kind,
			'attempts' => $attempts,
		);
	}

	public static function unfinished_save_reason() {
		return __( 'The save did not finish: the server stopped while saving this translation, usually because of a memory or time limit. The PHP error log has the details.', 'sitepress' );
	}

	private function watch_for_fatal( $tp_id, array $record ) {
		self::$job_in_flight = array(
			'tp_id'  => $tp_id,
			'record' => $record,
		);

		if ( ! self::$fatal_watch_registered ) {
			self::$fatal_watch_registered = true;
			register_shutdown_function( array( __CLASS__, 'record_fatal_of_job_in_flight' ) );
		}
	}

	public static function record_fatal_of_job_in_flight( $error = null ) {
		$in_flight = self::$job_in_flight;
		$error     = null === $error ? error_get_last() : $error;

		if ( ! $in_flight || ! is_array( $error ) || ! in_array( (int) $error['type'], self::FATAL_ERROR_TYPES, true ) ) {
			return;
		}

		self::$job_in_flight = null;

		$message = trim( (string) strtok( (string) $error['message'], "\n" ) );
		if ( false !== strpos( $message, 'Allowed memory size' ) ) {
			$limit  = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
			$wanted = memory_get_usage( true ) + 16 * 1024 * 1024;
			if ( $limit > 0 && $wanted > $limit ) {
				ini_set( 'memory_limit', (string) $wanted );
			}
		}

		$max = \WPML\TM\TranslationProxy\SendTuning::MAX_FAILURE_REASON_LENGTH;
		if ( strlen( $message ) > $max ) {
			$message = substr( $message, 0, $max ) . ' …[truncated]';
		}

		$record           = $in_flight['record'];
		$record['reason'] = sprintf(
			/* translators: Reason shown on the WPML translation dashboard for a translation whose save crashed. %s: PHP's error message, in English, e.g. "Allowed memory size of 268435456 bytes exhausted". */
			__( 'The save did not finish: the server stopped while saving this translation (%s).', 'sitepress' ),
			$message
		);

		\WPML\TM\TranslationProxy\ApplyFailureStore::put( $in_flight['tp_id'], $record );
	}

	private function sanitize_failure_reason( $e ) {
		$message = (string) strtok( (string) $e->getMessage(), "\n" );
		$parts   = preg_split( '/\s+Details:/', $message );
		$reason  = is_array( $parts ) && ! empty( $parts[0] ) ? $parts[0] : get_class( $e );
		$reason  = trim( $reason );

		$max = \WPML\TM\TranslationProxy\SendTuning::MAX_FAILURE_REASON_LENGTH;
		if ( strlen( $reason ) > $max ) {
			$reason = substr( $reason, 0, $max ) . ' …[truncated]';
		}

		return $reason;
	}

	private function has_in_progress_jobs( WPML_TM_Jobs_Collection $jobs ) {
		return count( $jobs->filter_by_status( ICL_TM_IN_PROGRESS ) ) > 0;
	}

	private function get_jobs( array $params ) {
		if ( $params ) {
			if ( isset( $params['original_element_id'], $params['element_type'] ) ) {
				$jobs = $this->get_jobs_by_original_element( $params['original_element_id'], $params['element_type'] );
			} else {
				$jobs = $this->get_jobs_by_ids( $params );
			}
		} else {
			$jobs = $this->get_all_ready_jobs();
		}

		return $jobs;
	}

	private function get_jobs_by_original_element( $original_element_id, $element_type ) {
		$params = new WPML_TM_Jobs_Search_Params();
		$params->set_scope( WPML_TM_Jobs_Search_Params::SCOPE_REMOTE );
		$params->set_original_element_id( $original_element_id );
		$params->set_job_types( $element_type );

		return $this->jobs_repository->get_collection( $params );
	}

	private function get_jobs_by_ids( array $params ) {
		$jobs = array();
		foreach ( $params as $param ) {
			$jobs[] = $this->jobs_repository->get_job( $param['id'], $param['type'] );
		}

		return new WPML_TM_Jobs_Collection( $jobs );
	}

	private function get_all_ready_jobs() {
		return $this->jobs_repository->get_collection(
			new WPML_TM_Jobs_Search_Params(
				array(
					'status' => array( ICL_TM_TRANSLATION_READY_TO_DOWNLOAD ),
					'scope'  => WPML_TM_Jobs_Search_Params::SCOPE_REMOTE,
				)
			)
		);
	}

	private function sync_jobs( WPML_TM_Jobs_Collection $jobs ) {
		$tp_ids = array();
		foreach ( $jobs as $job ) {
			$tp_id = $job->get_tp_id();
			if ( $tp_id ) {
				$tp_ids[] = $tp_id;
			}
		}

		if ( ! $tp_ids ) {
			return $jobs;
		}

		$synced_jobs = $this->tp_sync->sync( $tp_ids );
		foreach ( $jobs as $job ) {
			foreach ( $synced_jobs as $synced_job ) {
				if ( $job->is_equal( $synced_job ) ) {
					$job->set_status( $synced_job->get_status() );
					break;
				}
			}
		}

		return $jobs;
	}
}
