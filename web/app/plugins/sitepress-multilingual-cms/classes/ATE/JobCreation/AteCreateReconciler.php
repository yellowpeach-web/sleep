<?php

namespace WPML\TM\ATE\JobCreation;

use WPML\FP\Obj;
use WPML\TM\API\Job\Map;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\Cancel\PendingCancels;
use WPML\TM\ATE\JobRecords;
use WPML\TM\Jobs\JobLog;

class AteCreateReconciler {

	const OUTCOME_ADOPTED   = 'adopted';
	const OUTCOME_NONE      = 'none';
	const OUTCOME_NO_ANSWER = 'no_answer';
	const OUTCOME_NO_LOOKUP = 'no_lookup';

	const UNCONFIRMED_OPTION_PREFIX = 'wpml_tm_ate_create_unconfirmed_';

	const IN_FLIGHT_OPTION_PREFIX = 'wpml_tm_ate_create_in_flight_';

	const ATE_RECORD_SPENT_STATUSES = [ 8, 20, 34, 40, 42 ];

	const NO_LOOKUP_ERROR_CODES = [ 'ate_auth_failed', 'auth_error' ];

	const OWN_ERROR_NO_ANSWER_CODES = [ 'http_request_failed', 'wpml_ate_reconnecting' ];

	const NO_ANSWER_HTTP_STATUSES = [ 408, 425, 429 ];

	const UNREADABLE_OPTION_PREFIX = 'wpml_tm_ate_lookup_unreadable_';

	const UNREADABLE_SEND_ANYWAY_COUNT = 3;

	const UNREADABLE_SEND_ANYWAY_SECONDS = 600;

	const UNREADABLE_ANSWER_LENGTH = 500;

	private $ateApi;

	private $ateJobs;

	public function __construct( \WPML_TM_ATE_API $ateApi, \WPML_TM_ATE_Jobs $ateJobs ) {
		$this->ateApi  = $ateApi;
		$this->ateJobs = $ateJobs;
	}

	public function reconcile( array $jobIds ) {
		global $wpdb;

		$jobIds = array_values( array_unique( array_map( 'intval', $jobIds ) ) );

		$ridByJob = [];
		foreach ( $jobIds as $jobId ) {
			$rid = (int) Map::ridFromDB( $jobId );
			if ( 0 === $rid && ! empty( $wpdb->last_error ) ) {
				return $this->noAnswer( [ $jobId => 0 ], 'rid_read_failed', $wpdb->last_error );
			}
			if ( $jobId > 0 && $rid > 0 ) {
				$ridByJob[ $jobId ] = $rid;
			}
		}

		if ( ! $ridByJob ) {
			return self::result( self::OUTCOME_NONE );
		}

		try {
			$response = $this->ateApi->get_jobs_by_wpml_ids( array_values( array_unique( $ridByJob ) ) );
		} catch ( \Throwable $throwable ) {
			return $this->noAnswer( $ridByJob, 'throw', $throwable->getMessage() );
		}

		if ( $response instanceof \WP_Error ) {
			return $this->fromError( $ridByJob, $response );
		}

		if ( ! is_object( $response ) && ! is_array( $response ) ) {
			return $this->unreadable(
				$ridByJob,
				'not_an_object',
				is_scalar( $response ) ? (string) $response : gettype( $response )
			);
		}

		self::clearUnreadable( array_keys( $ridByJob ) );

		$adopted = $this->adoptable( $ridByJob, $response );

		return $adopted
			? self::result( self::OUTCOME_ADOPTED, $adopted )
			: self::result( self::OUTCOME_NONE );
	}

	public function bind( array $adopted, array $modeByJobId, $sentFrom ) {
		if ( ! $adopted ) {
			return;
		}

		$this->ateJobs->warm_cache( array_keys( $adopted ) );

		$oldEditor = wpml_tm_load_old_jobs_editor();

		foreach ( $adopted as $wpmlJobId => $ateJobId ) {
			$wpmlJobId = (int) $wpmlJobId;
			$ateJobId  = (int) $ateJobId;

			$this->ateJobs->store( $wpmlJobId, [ JobRecords::FIELD_ATE_JOB_ID => $ateJobId ] );
			$oldEditor->set( $wpmlJobId, \WPML_TM_Editors::ATE );
			self::clearUnreadable( [ $wpmlJobId ] );
			$translationJob = wpml_tm_load_job_factory()->get_translation_job( $wpmlJobId, false, 0, true );

			$mode = isset( $modeByJobId[ $wpmlJobId ] ) ? $modeByJobId[ $wpmlJobId ] : null;
			if ( null !== $mode ) {
				$wasParked = in_array( Jobs::getStatus( $wpmlJobId ), [ ICL_TM_ATE_NEEDS_RETRY, ICL_TM_NOT_TRANSLATED ], true );

				Jobs::setAutomaticStatus( $wpmlJobId, 'auto' === $mode );

				if ( $wasParked ) {
					Jobs::setStatus( $wpmlJobId, 'auto' === $mode ? ICL_TM_IN_PROGRESS : ICL_TM_WAITING_FOR_TRANSLATOR );
				}
			}

			JobLog::add(
				'ate_job_bound',
				[
					'job_id'              => $wpmlJobId,
					'ate_job_id'          => $ateJobId,
					'sent_from'           => $sentFrom,
					'target_lang'         => JobLog::safeCall( $translationJob, 'get_language_code' ),
					'original_element_id' => JobLog::safeCall( $translationJob, 'get_original_element_id' ),
				]
			);
		}

		/* translators: Message shown after content is handed to the Advanced Translation Editor. %1$s: how many translation jobs were handed over. */
		$message = __( '%1$s jobs added to the Advanced Translation Editor.', 'sitepress' );
		do_action( 'wpml_tm_add_message', 'updated', sprintf( $message, count( $adopted ) ), 'wpml_tm_ate_create_job' );

		do_action( 'wpml_tm_ate_jobs_created', $adopted );
	}

	public static function markedMode( $wpmlJobId ) {
		$mark = get_option( self::UNCONFIRMED_OPTION_PREFIX . (int) $wpmlJobId, false );

		if ( ! is_array( $mark ) || ! isset( $mark['mode'] ) || ! in_array( $mark['mode'], [ 'auto', 'manual' ], true ) ) {
			return null;
		}

		return $mark['mode'];
	}

	public static function markedByClone( $wpmlJobId ) {
		$mark = get_option( self::UNCONFIRMED_OPTION_PREFIX . (int) $wpmlJobId, false );

		return is_array( $mark ) && isset( $mark['clone'] ) && (int) $mark['clone'] > 0;
	}

	public static function jobTypeOf( $translationJob, $modeSetInDashboard ) {
		$document = $translationJob->get_original_document();
		if ( ! $document ) {
			return 'manual';
		} elseif ( $modeSetInDashboard ) {
			return $modeSetInDashboard;
		} else {
			return Jobs::isEligibleForAutomaticTranslations( $translationJob->get_id() ) ? 'auto' : 'manual';
		}
	}

	private function adoptable( array $ridByJob, $response ) {
		$adopted = [];
		$refused = [];
		$cancelOwed = PendingCancels::pending();
		foreach ( $ridByJob as $jobId => $rid ) {
			$entry = null;
			if ( is_object( $response ) && isset( $response->{$rid} ) ) {
				$entry = $response->{$rid};
			} elseif ( is_array( $response ) && isset( $response[ $rid ] ) ) {
				$entry = $response[ $rid ];
			}

			$ateJobId = (int) Obj::prop( 'ate_job_id', $entry );
			if ( $ateJobId <= 0 ) {
				continue;
			}

			$maker = (int) Obj::prop( 'wpml_job_id', $entry );
			if ( $maker && $maker !== $jobId && ! $this->isRecordBehind( $jobId, $rid, $maker ) ) {
				$refused[ $jobId ] = [ 'rid' => $rid, 'ate_job_id' => $ateJobId, 'reason' => 'other_job', 'made_by' => $maker ];
				continue;
			}

			$newest = (int) Map::jobIdFromDB( $rid );
			if ( $newest && $newest !== $jobId ) {
				$refused[ $jobId ] = [ 'rid' => $rid, 'ate_job_id' => $ateJobId, 'reason' => 'superseded', 'newest_job_id' => $newest ];
				continue;
			}

			$holder = (int) $this->ateJobs->get_wpml_job_id( $ateJobId );
			if ( $holder && $holder !== $jobId ) {
				$refused[ $jobId ] = [ 'rid' => $rid, 'ate_job_id' => $ateJobId, 'reason' => 'held', 'held_by' => $holder ];
				continue;
			}

			$ateStatus = Obj::prop( 'status_id', $entry );
			if ( null === $ateStatus ) {
				$ateStatus = Obj::prop( 'status', $entry );
			}
			if ( null !== $ateStatus && in_array( (int) $ateStatus, self::ATE_RECORD_SPENT_STATUSES, true ) ) {
				$refused[ $jobId ] = [ 'rid' => $rid, 'ate_job_id' => $ateJobId, 'reason' => 'spent', 'ate_status' => (int) $ateStatus ];
				continue;
			}

			if ( in_array( $ateJobId, $cancelOwed, true ) ) {
				$refused[ $jobId ] = [ 'rid' => $rid, 'ate_job_id' => $ateJobId, 'reason' => 'cancel_owed' ];
				continue;
			}

			$adopted[ $jobId ] = $ateJobId;
		}

		if ( $refused ) {
			JobLog::add(
				'ate_job_adoption_refused',
				[
					'jobs'  => $refused,
					'count' => count( $refused ),
				]
			);
		}

		if ( $adopted ) {
			JobLog::add(
				'ate_jobs_adopted_on_retry',
				[
					'jobs'  => $adopted,
					'count' => count( $adopted ),
				]
			);
		}

		return $adopted;
	}

	private function isRecordBehind( $jobId, $rid, $recordJobId ) {
		$previous = Jobs::getPreviousJob( (int) $jobId );

		return $previous
		       && (int) Obj::prop( 'job_id', $previous ) === (int) $recordJobId
		       && (int) Obj::prop( 'rid', $previous ) === (int) $rid
		       && \WPML\TM\Jobs\TranslationRecord::isCompleted( $previous );
	}

	private function fromError( array $ridByJob, \WP_Error $error ) {
		$code = $error->get_error_code();

		if ( is_numeric( $code ) ) {
			$status = (int) $code;
			if ( $status >= 400 && $status < 500 && ! in_array( $status, self::NO_ANSWER_HTTP_STATUSES, true ) ) {
				return $this->noLookup( $ridByJob, $error );
			}

			return $this->noAnswer( $ridByJob, (string) $code, $error->get_error_message() );
		}

		if ( in_array( $code, self::NO_LOOKUP_ERROR_CODES, true ) ) {
			return $this->noLookup( $ridByJob, $error );
		}

		if ( in_array( $code, self::OWN_ERROR_NO_ANSWER_CODES, true ) ) {
			return self::result( self::OUTCOME_NO_ANSWER, [], $error );
		}

		return $this->unreadable( $ridByJob, (string) $code, $error->get_error_message() );
	}

	private function unreadable( array $ridByJob, $code, $answer ) {
		$now     = time();
		$excerpt = self::redact( mb_substr( (string) $answer, 0, self::UNREADABLE_ANSWER_LENGTH ) );

		foreach ( array_keys( $ridByJob ) as $jobId ) {
			$record = self::unreadableRecord( $jobId );
			update_option(
				self::UNREADABLE_OPTION_PREFIX . (int) $jobId,
				[
					'first'  => $record ? (int) $record['first'] : $now,
					'last'   => $now,
					'count'  => $record ? (int) $record['count'] + 1 : 1,
					'code'   => (string) $code,
					'answer' => $excerpt,
				],
				false
			);
		}

		$result               = $this->noAnswer( $ridByJob, $code, $answer );
		$result['unreadable'] = true;

		return $result;
	}

	public static function unreadableRecord( $wpmlJobId ) {
		$record = get_option( self::UNREADABLE_OPTION_PREFIX . (int) $wpmlJobId, false );

		if ( ! is_array( $record ) || ! isset( $record['first'], $record['count'] ) ) {
			return null;
		}

		return [
			'first'  => (int) $record['first'],
			'last'   => (int) ( $record['last'] ?? $record['first'] ),
			'count'  => (int) $record['count'],
			'code'   => (string) ( $record['code'] ?? '' ),
			'answer' => (string) ( $record['answer'] ?? '' ),
		];
	}

	public static function sendAnywayDue( $wpmlJobId ) {
		$record = self::unreadableRecord( $wpmlJobId );

		return null !== $record
			&& $record['count'] >= self::UNREADABLE_SEND_ANYWAY_COUNT
			&& $record['last'] - $record['first'] >= self::UNREADABLE_SEND_ANYWAY_SECONDS;
	}

	public static function clearUnreadable( array $wpmlJobIds ) {
		foreach ( $wpmlJobIds as $wpmlJobId ) {
			delete_option( self::UNREADABLE_OPTION_PREFIX . (int) $wpmlJobId );
		}
	}

	public static function unreadableRecords( $limit = 20 ) {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! is_callable( [ $wpdb, 'get_col' ] ) || ! is_callable( [ $wpdb, 'prepare' ] ) ) {
			return [];
		}

		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id DESC LIMIT %d",
				addcslashes( self::UNREADABLE_OPTION_PREFIX, '_%\\' ) . '%',
				(int) $limit
			)
		);

		$records = [];
		foreach ( (array) $names as $name ) {
			$jobId  = (int) substr( (string) $name, strlen( self::UNREADABLE_OPTION_PREFIX ) );
			$record = $jobId ? self::unreadableRecord( $jobId ) : null;
			if ( $record ) {
				$records[ $jobId ] = $record;
			}
		}

		return $records;
	}

	private static function redact( $text ) {
		$names = '(?:accesskey|access_key|api_key|apikey|shared_key|shared|private_key|secret|token|signature|authorization)';

		return (string) preg_replace(
			[
				'/(' . $names . ')(=|%3D)[^&\s"\'<>]+/i',
				'/("' . $names . '"\s*:\s*")[^"]*"/i',
				'/(Bearer\s+)[A-Za-z0-9\-\._~\+\/]+=*/i',
			],
			[ '$1$2REDACTED', '$1REDACTED"', '$1REDACTED' ],
			$text
		);
	}

	private function noAnswer( array $ridByJob, $code, $message ) {
		JobLog::addError(
			'ate_lookup_no_answer',
			[
				'jobs'    => $ridByJob,
				'code'    => $code,
				'message' => $message,
			]
		);

		return self::result( self::OUTCOME_NO_ANSWER, [], CreateFailureText::noAnswerError() );
	}

	private function noLookup( array $ridByJob, \WP_Error $error ) {
		JobLog::addError(
			'ate_lookup_unavailable',
			[
				'jobs'    => $ridByJob,
				'code'    => $error->get_error_code(),
				'message' => $error->get_error_message(),
			]
		);

		return self::result( self::OUTCOME_NO_LOOKUP, [], $error );
	}

	private static function result( $outcome, array $adopted = [], $error = null ) {
		return [
			'outcome' => $outcome,
			'adopted' => $adopted,
			'error'   => $error,
		];
	}
}
