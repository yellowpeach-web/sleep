<?php

namespace WPML\TM\TranslationProxy;

use WPML\Legacy\Component\TranslationProxy\Application\Service\SendLock;
use WPML\LIB\WP\User;
use WPML\TM\Jobs\JobLog;

class StrandedBatchRecovery {

	const COMMITTED = 'committed';

	const ALREADY_COMMITTED = 'already_committed';

	const RELEASED = 'released';

	const UNDECIDED = 'undecided';

	const SEND_ENDED_AFTER_SECONDS = 120;

	const MAX_BATCHES_PER_RUN = 5;

	const BACKOFF_SECONDS = 600;

	const BACKOFF_TRANSIENT = 'wpml_tp_stranded_batch_backoff';

	const DASHBOARD_PAGE = 'tm/menu/main.php';

	private $wpdb;

	private $jobsApi;

	private $project;

	private $releasePair;

	private $serviceId;

	public function __construct( $wpdb, $jobsApi, $project, callable $releasePair, $serviceId ) {
		$this->wpdb        = $wpdb;
		$this->jobsApi     = $jobsApi;
		$this->project     = $project;
		$this->releasePair = $releasePair;
		$this->serviceId   = (string) $serviceId;
	}

	public static function onDashboardLoad() {
		if ( ! self::isTranslationDashboard() || get_transient( self::BACKOFF_TRANSIENT ) ) {
			return;
		}

		$lock = new SendLock();
		if ( ! $lock->acquire() ) {
			return;
		}

		try {
			$recovery = self::create( null );
			if ( $recovery ) {
				$recovery->run( null );
			}
		} catch ( \Throwable $e ) {
			return;
		} finally {
			$lock->release();
		}
	}

	public static function beforeNewBatch( $project, $batchName ) {
		try {
			$recovery = self::create( $project );
			if ( $recovery ) {
				$recovery->run( $batchName );
			}
		} catch ( \Throwable $e ) {
			return;
		}
	}

	private static function create( $project ) {
		global $wpdb;

		if ( ! $wpdb || ! class_exists( '\TranslationProxy' ) ) {
			return null;
		}

		$serviceId = \TranslationProxy::get_current_service_id();
		if ( ! $serviceId ) {
			return null;
		}

		if ( ! $project ) {
			$service = \TranslationProxy::get_current_service();
			if ( ! $service || is_wp_error( $service ) ) {
				return null;
			}
			$project = new \TranslationProxy_Project( $service, 'xmlrpc', \TranslationProxy::get_tp_client() );
		}

		return new self(
			$wpdb,
			wpml_tm_get_tp_jobs_api(),
			$project,
			function ( $translationId ) {
				wpml_load_core_tm()->cancel_translation_request( (int) $translationId );
			},
			(string) $serviceId
		);
	}

	public function run( $exceptBatchName = null, $now = null ) {
		$now     = null === $now ? time() : (int) $now;
		$batches = $this->candidates( $exceptBatchName, $now );

		if ( ! $batches ) {
			return [];
		}

		JobLog::maybeInitRequest();
		JobLog::createNewGroup( JobLog::GROUP_ID_SEND_JOBS, 'TP stranded batch recovery', [ 'batches' => count( $batches ) ] );

		$outcomes = [];
		foreach ( $batches as $batchId => $batch ) {
			$outcomes[ $batchId ] = $this->recover( $batchId, $batch['name'], $batch['pairs'] );
		}

		if ( in_array( self::UNDECIDED, $outcomes, true ) ) {
			set_transient( self::BACKOFF_TRANSIENT, 1, self::BACKOFF_SECONDS );
		}

		JobLog::finishCurrentGroup();

		return $outcomes;
	}

	private function candidates( $exceptBatchName, $now ) {
		$wpdb      = $this->wpdb;
		$serviceId = $this->serviceId;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.id AS batch_id, b.batch_name, ts.translation_id, ts.tp_id, ts.timestamp
				 FROM {$wpdb->prefix}icl_translation_batches b
				 JOIN {$wpdb->prefix}icl_translation_status ts ON ts.batch_id = b.id
				 WHERE ( b.tp_id IS NULL OR b.tp_id = 0 )
				   AND ts.tp_id > 0
				   AND ts.status IN ( %d, %d )
				   AND ts.translation_service = %s
				 ORDER BY b.id
				 LIMIT 500",
				(int) ICL_TM_WAITING_FOR_TRANSLATOR,
				(int) ICL_TM_IN_PROGRESS,
				$serviceId
			)
		);

		$batches = [];
		$latest  = [];
		foreach ( (array) $rows as $row ) {
			$batchId = (int) $row->batch_id;
			if ( null !== $exceptBatchName && (string) $row->batch_name === (string) $exceptBatchName ) {
				continue;
			}
			if ( ! isset( $batches[ $batchId ] ) ) {
				$batches[ $batchId ] = [
					'name'  => (string) $row->batch_name,
					'pairs' => [],
				];
				$latest[ $batchId ]  = 0;
			}
			$batches[ $batchId ]['pairs'][ (int) $row->translation_id ] = (int) $row->tp_id;
			$latest[ $batchId ] = max( $latest[ $batchId ], (int) strtotime( $row->timestamp . ' UTC' ) );
		}

		$ended = array_filter(
			$batches,
			function ( $batchId ) use ( $latest, $now ) {
				return $latest[ $batchId ] <= $now - self::SEND_ENDED_AFTER_SECONDS;
			},
			ARRAY_FILTER_USE_KEY
		);

		return array_slice( $ended, 0, self::MAX_BATCHES_PER_RUN, true );
	}

	private function recover( $batchId, $batchName, array $pairs ) {
		$log = [
			'batchId'   => $batchId,
			'batchName' => $batchName,
			'jobs'      => count( $pairs ),
		];

		try {
			$statuses = $this->jobsApi->get_jobs_statuses( array_values( $pairs ) );
		} catch ( \Throwable $e ) {
			JobLog::addError( 'tp_stranded_batch_undecided', $log + [ 'reason' => 'statuses_unreadable' ] );

			return self::UNDECIDED;
		}

		if ( ! is_array( $statuses ) || ! $statuses ) {
			JobLog::addError( 'tp_stranded_batch_undecided', $log + [ 'reason' => 'no_statuses' ] );

			return self::UNDECIDED;
		}

		$statuses = array_filter(
			$statuses,
			function ( $status ) use ( $pairs ) {
				return in_array( (int) $status->get_tp_id(), $pairs, true );
			}
		);

		$tpBatchIds = [];
		$dispatched = false;
		foreach ( $statuses as $status ) {
			$tpBatchIds[] = (int) $status->get_batch_id();
			if ( \WPML_TP_Job_States::RECEIVED !== $status->get_status() ) {
				$dispatched = true;
			}
		}
		$tpBatchIds = array_values( array_unique( array_filter( $tpBatchIds ) ) );

		if ( 1 !== count( $tpBatchIds ) ) {
			JobLog::addError( 'tp_stranded_batch_undecided', $log + [ 'reason' => 'tp_batch_unknown' ] );

			return self::UNDECIDED;
		}

		$tpBatchId = $tpBatchIds[0];
		$log      += [ 'tpBatchId' => $tpBatchId ];

		if ( $dispatched ) {
			$this->wpdb->update( $this->wpdb->prefix . 'icl_translation_batches', [ 'tp_id' => $tpBatchId ], [ 'id' => $batchId ] );
			JobLog::add( 'tp_stranded_batch_already_committed', $log );

			return self::ALREADY_COMMITTED;
		}

		try {
			$response = $this->project->commit_batch_job( $tpBatchId, false, $batchName );
		} catch ( \Throwable $e ) {
			JobLog::addError( 'tp_stranded_batch_undecided', $log + [ 'reason' => 'commit_error' ] );

			return self::UNDECIDED;
		}

		$this->unpin( $tpBatchId );

		if ( $response ) {
			JobLog::add( 'tp_stranded_batch_committed', $log );

			return self::COMMITTED;
		}

		foreach ( array_keys( $pairs ) as $translationId ) {
			call_user_func( $this->releasePair, $translationId );
		}
		JobLog::addError( 'tp_stranded_batch_released', $log );

		return self::RELEASED;
	}

	private function unpin( $tpBatchId ) {
		$pinned = TpBatchState::getBatchData();
		if ( $pinned && is_object( $pinned ) && method_exists( $pinned, 'get_id' ) && (int) $pinned->get_id() === (int) $tpBatchId ) {
			TpBatchState::forgetBatch();
		}
	}

	private static function isTranslationDashboard() {
		if ( ! is_admin() || wp_doing_ajax() || self::DASHBOARD_PAGE !== filter_input( INPUT_GET, 'page' ) ) {
			return false;
		}

		foreach ( [ 'sm', 'tab' ] as $param ) {
			$value = filter_input( INPUT_GET, $param );
			if ( null !== $value && 'dashboard' !== $value ) {
				return false;
			}
		}

		return User::currentUserCan( User::CAP_MANAGE_TRANSLATIONS ) || User::currentUserIsAdmin();
	}
}
