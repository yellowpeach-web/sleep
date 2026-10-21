<?php

namespace WPML\TM\ATE\Retranslation\BackgroundTask;

use WPML\Core\BackgroundTask\Command\DeleteBackgroundTask;
use WPML\Core\BackgroundTask\Command\PersistBackgroundTask;
use WPML\Core\BackgroundTask\Command\UpdateBackgroundTask;
use WPML\Core\BackgroundTask\Model\BackgroundTask;
use WPML\Core\BackgroundTask\Repository\BackgroundTaskRepository;
use WPML\TM\ATE\Retranslation\RetranslationInfo;
use WPML\TM\Jobs\JobLog;
use function WPML\Container\make;

class TaskManager {

	const CREATE_LOCK_NAME    = 'retranslation-task-create';
	const CREATE_LOCK_TIMEOUT = 10;
	const SYNC_TIMEOUT        = 86400;

	private $repository;

	private $persistCommand;

	private $updateCommand;

	private $deleteCommand;

	private $wpdb;

	public function __construct(
		BackgroundTaskRepository $repository,
		PersistBackgroundTask $persistCommand,
		UpdateBackgroundTask $updateCommand,
		DeleteBackgroundTask $deleteCommand,
		\wpdb $wpdb
	) {
		$this->repository     = $repository;
		$this->persistCommand = $persistCommand;
		$this->updateCommand  = $updateCommand;
		$this->deleteCommand  = $deleteCommand;
		$this->wpdb           = $wpdb;
	}

	public function getOrCreateRetranslationTask( ?int $requestId = null ) {
		$existing = $this->getRetranslationTask( $requestId );
		if ( $existing ) {
			JobLog::addRetranslationEvent( 'retranslation_task_get_or_create', [
				'result'     => 'reused',
				'request_id' => $requestId,
				'task'       => $this->getTaskLogData( $existing ),
			] );

			return $existing;
		}

		$lock = make( 'WPML\Utilities\Lock', [ ':name' => self::CREATE_LOCK_NAME ] );
		if ( ! $lock->create( self::CREATE_LOCK_TIMEOUT ) ) {
			$existing = $this->getRetranslationTask( $requestId );

			JobLog::addRetranslationEvent( 'retranslation_task_get_or_create', [
				'result'     => $existing ? 'reused_after_lock_busy' : 'lock_busy_no_task',
				'request_id' => $requestId,
				'task'       => $this->getTaskLogData( $existing ),
			] );

			return $existing;
		}

		try {
			$existing = $this->getRetranslationTask( $requestId );
			if ( $existing ) {
				JobLog::addRetranslationEvent( 'retranslation_task_get_or_create', [
					'result'     => 'reused_after_lock',
					'request_id' => $requestId,
					'task'       => $this->getTaskLogData( $existing ),
				] );

				return $existing;
			}

			$task = $this->persistCommand->run(
				RetranslationTask::class,
				BackgroundTask::TASK_STATUS_PAUSED,
				1,
				[
					'phase'                    => RetranslationTask::PHASE_PREPARING,
					'kind'                     => 'suggestions',
					'request_id'               => $requestId,
					'suggestions_count'        => null,
					'ate_job_count'            => null,
					'ate_processed_job_count'  => null,
					'wpml_job_ids'             => [],
					'wpml_completed_job_ids'   => [],
					'created_at'               => time(),
					'failures'                 => 0,
				],
				[]
			);

			JobLog::addRetranslationEvent( 'retranslation_task_get_or_create', [
				'result'     => 'created',
				'request_id' => $requestId,
				'task'       => $this->getTaskLogData( $task ),
			] );

			return $task;
		} finally {
			$lock->release();
		}
	}

	public function getRetranslationTask( ?int $requestId = null ) {
		$task = $this->repository->getLastIncompletedByType( RetranslationTask::class );
		if ( ! $task || ! $requestId ) {
			return $task;
		}

		$payload = $task->getPayload();
		$storedRequestId = ! empty( $payload['request_id'] ) ? (int) $payload['request_id'] : null;

		return null === $storedRequestId || $storedRequestId === $requestId ? $task : null;
	}

	public function updateFromAteInfo( RetranslationInfo $info ) {
		$task = $this->getOrCreateRetranslationTask( $info->getRequestId() );
		if ( ! $task ) {
			return null;
		}

		$payload = $task->getPayload();
		$payload['phase']                   = $info->isCompleted() ? RetranslationTask::PHASE_FINALIZING : RetranslationTask::PHASE_ATE_PROCESSING;
		$payload['request_id']              = $info->getRequestId();
		$payload['failures']                = 0;

		if ( null !== $info->getSuggestionsCount() ) {
			$payload['suggestions_count'] = $info->getSuggestionsCount();
		}

		if ( null !== $info->getJobCount() ) {
			$jobCount = max( 1, $info->getJobCount() );
			$payload['ate_job_count']           = $jobCount;
			$payload['ate_processed_job_count'] = min( (int) $info->getProcessedJobCount(), $jobCount );
			$task->setTotalCount( $jobCount );
			$task->setCompletedCount( $info->isCompleted() ? $jobCount : $payload['ate_processed_job_count'] );
		}

		if ( $info->isCompleted() && empty( $payload['finalizing_since'] ) ) {
			$payload['finalizing_since'] = time();
		}

		$task->setPayload( $payload );
		$this->update( $task );

		return $task;
	}

	public function addJobsToSyncBatch( array $freshWpmlJobIds ) {
		$freshWpmlJobIds = array_values( array_unique( array_filter( array_map( 'intval', $freshWpmlJobIds ) ) ) );
		$task            = $this->getRetranslationTask();
		$taskSource      = $task ? 'active' : null;

		if ( ! $task ) {
			$task = $this->getLatestTaskByPhase( RetranslationTask::PHASE_FINALIZING );
			$taskSource = $task ? 'finalizing_fallback' : null;
		}

		if ( ! $task ) {
			JobLog::addRetranslationEvent( 'retranslation_sync_jobs_added_to_task', [
				'result'                => 'no_task',
				'fresh_wpml_job_count'  => count( $freshWpmlJobIds ),
			] );

			return null;
		}

		if ( ! $freshWpmlJobIds ) {
			JobLog::addRetranslationEvent( 'retranslation_sync_jobs_added_to_task', [
				'result'               => 'no_fresh_jobs',
				'task_source'          => $taskSource,
				'fresh_wpml_job_count' => 0,
				'task'                 => $this->getTaskLogData( $task ),
			] );

			return $task;
		}

		$payload                 = $task->getPayload();
		$oldPhase                = isset( $payload['phase'] ) ? $payload['phase'] : null;
		$existing                = isset( $payload['wpml_job_ids'] ) ? (array) $payload['wpml_job_ids'] : [];
		$payload['wpml_job_ids'] = array_values( array_unique( array_merge( array_map( 'intval', $existing ), $freshWpmlJobIds ) ) );
		$payload['phase']        = RetranslationTask::PHASE_WPML_SYNCING;
		$payload['sync_started_at'] = isset( $payload['sync_started_at'] ) ? (int) $payload['sync_started_at'] : time();

		$completed = isset( $payload['wpml_completed_job_ids'] ) ? array_map( 'intval', (array) $payload['wpml_completed_job_ids'] ) : [];
		$completed = array_values( array_intersect( $completed, $payload['wpml_job_ids'] ) );

		$task->setStatus( BackgroundTask::TASK_STATUS_PAUSED );
		$task->setPayload( $payload );
		$task->setCompletedIds( $completed );
		$task->setTotalCount( count( $payload['wpml_job_ids'] ) );
		$task->setCompletedCount( count( $completed ) );
		$this->update( $task );

		JobLog::addRetranslationEvent( 'retranslation_sync_jobs_added_to_task', [
			'result'               => 'updated',
			'task_source'          => $taskSource,
			'old_phase'            => $oldPhase,
			'new_phase'            => $payload['phase'],
			'fresh_wpml_job_count' => count( $freshWpmlJobIds ),
			'total_wpml_job_count' => count( $payload['wpml_job_ids'] ),
			'sync_started_at'      => $payload['sync_started_at'],
			'task'                 => $this->getTaskLogData( $task ),
		] );

		return $task;
	}

	private function getLatestTaskByPhase( $phase ) {
		$wpdb  = $this->wpdb;
		$table = $wpdb->prefix . BackgroundTask::TABLE_NAME;
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM `' . esc_sql( $table ) . '` WHERE task_type = %s ORDER BY task_id DESC LIMIT 20',
				RetranslationTask::class
			),
			ARRAY_A
		);

		foreach ( (array) $rows as $row ) {
			$payload = ! empty( $row['payload'] ) ? unserialize( $row['payload'], [ 'allowed_classes' => false ] ) : [];
			if ( is_array( $payload ) && $phase === ( $payload['phase'] ?? null ) ) {
				return $this->repository->createFromQueryResult( $row );
			}
		}

		return null;
	}

	public function completeDrainIfNoJobsToSync() {
		$task = $this->getRetranslationTask();
		if ( ! $task ) {
			JobLog::addRetranslationEvent( 'retranslation_drain_completion_check', [
				'result' => 'no_task',
			] );

			return;
		}

		$payload = $task->getPayload();
		if ( RetranslationTask::PHASE_FINALIZING !== ( $payload['phase'] ?? null ) ) {
			JobLog::addRetranslationEvent( 'retranslation_drain_completion_check', [
				'result' => 'not_finalizing',
				'task'   => $this->getTaskLogData( $task ),
			] );

			return;
		}

		$wpmlJobIds = isset( $payload['wpml_job_ids'] ) ? array_filter( (array) $payload['wpml_job_ids'] ) : [];
		if ( ! $wpmlJobIds ) {
			$this->finish( $task );

			JobLog::addRetranslationEvent( 'retranslation_drain_completion_check', [
				'result' => 'completed_no_wpml_jobs',
				'task'   => $this->getTaskLogData( $task ),
			] );
		} else {
			JobLog::addRetranslationEvent( 'retranslation_drain_completion_check', [
				'result'         => 'waiting_for_wpml_jobs',
				'wpml_job_count' => count( $wpmlJobIds ),
				'task'           => $this->getTaskLogData( $task ),
			] );
		}
	}

	public function recordJobsApplied( array $appliedWpmlJobIds ) {
		$appliedWpmlJobIds = array_values( array_unique( array_filter( array_map( 'intval', $appliedWpmlJobIds ) ) ) );
		if ( ! $appliedWpmlJobIds ) {
			JobLog::addRetranslationEvent( 'retranslation_downloaded_jobs_applied', [
				'result'           => 'no_downloaded_job_ids',
				'downloaded_count' => 0,
			] );

			return;
		}

		$wpdb  = $this->wpdb;
		$table = $wpdb->prefix . BackgroundTask::TABLE_NAME;

		$wpdb->query( 'START TRANSACTION' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT task_id, task_status, total_count, completed_ids, payload FROM `' . esc_sql( $table ) . '` WHERE task_type = %s AND task_status != %d ORDER BY task_id DESC LIMIT 1 FOR UPDATE',
				RetranslationTask::class,
				BackgroundTask::TASK_STATUS_COMPLETED
			),
			ARRAY_A
		);

		if ( ! $row ) {
			$wpdb->query( 'COMMIT' );

			JobLog::addRetranslationEvent( 'retranslation_downloaded_jobs_applied', [
				'result'           => 'no_active_task',
				'downloaded_count' => count( $appliedWpmlJobIds ),
			] );

			return;
		}

		$payload = $row['payload'] ? unserialize( $row['payload'], [ 'allowed_classes' => false ] ) : [];
		if ( ! is_array( $payload ) || RetranslationTask::PHASE_WPML_SYNCING !== ( $payload['phase'] ?? null ) ) {
			$wpdb->query( 'COMMIT' );

			JobLog::addRetranslationEvent( 'retranslation_downloaded_jobs_applied', [
				'result'           => 'task_not_syncing',
				'downloaded_count' => count( $appliedWpmlJobIds ),
				'task_id'          => (int) $row['task_id'],
				'status_before'    => (int) $row['task_status'],
				'phase'            => is_array( $payload ) && isset( $payload['phase'] ) ? $payload['phase'] : null,
			] );

			return;
		}

		$membership = isset( $payload['wpml_job_ids'] ) ? array_map( 'intval', (array) $payload['wpml_job_ids'] ) : [];
		$completed  = isset( $payload['wpml_completed_job_ids'] ) ? array_map( 'intval', (array) $payload['wpml_completed_job_ids'] ) : [];

		if ( ! $completed && ! empty( $row['completed_ids'] ) ) {
			$completed = array_map( 'intval', (array) unserialize( $row['completed_ids'], [ 'allowed_classes' => false ] ) );
		}

		$merged = array_values( array_unique( array_merge( $completed, array_intersect( $appliedWpmlJobIds, $membership ) ) ) );
		$matchedCount = count( array_intersect( $appliedWpmlJobIds, $membership ) );

		if ( count( $merged ) === count( $completed ) ) {
			$wpdb->query( 'COMMIT' );

			JobLog::addRetranslationEvent( 'retranslation_downloaded_jobs_applied', [
				'result'                 => 'no_new_matches',
				'downloaded_count'       => count( $appliedWpmlJobIds ),
				'matching_count'         => $matchedCount,
				'completed_count_before' => count( $completed ),
				'total_count'            => count( $membership ),
				'task_id'                => (int) $row['task_id'],
				'status_before'          => (int) $row['task_status'],
			] );

			return;
		}

		$totalCount                         = count( $membership );
		$completedCount                     = min( count( $merged ), $totalCount );
		$payload['wpml_completed_job_ids']  = $merged;
		$status                             = $completedCount >= $totalCount ? BackgroundTask::TASK_STATUS_COMPLETED : (int) $row['task_status'];

		$wpdb->query(
			$wpdb->prepare(
				'UPDATE `' . esc_sql( $table ) . '` SET total_count = %d, completed_count = %d, completed_ids = %s, payload = %s, task_status = %d WHERE task_id = %d',
				$totalCount,
				$completedCount,
				serialize( $merged ),
				serialize( $payload ),
				$status,
				(int) $row['task_id']
			)
		);

		$wpdb->query( 'COMMIT' );

		JobLog::addRetranslationEvent( 'retranslation_downloaded_jobs_applied', [
			'result'                 => $status === BackgroundTask::TASK_STATUS_COMPLETED ? 'completed' : 'progressed',
			'downloaded_count'       => count( $appliedWpmlJobIds ),
			'matching_count'         => $matchedCount,
			'completed_count_before' => count( $completed ),
			'completed_count_after'  => $completedCount,
			'total_count'            => $totalCount,
			'task_id'                => (int) $row['task_id'],
			'status_before'          => (int) $row['task_status'],
			'status_after'           => $status,
		] );
	}

	public function sweepRetranslationTask() {
		$task = $this->getRetranslationTask();
		if ( ! $task ) {
			JobLog::addRetranslationEvent( 'retranslation_task_sweep', [
				'result' => 'no_task',
			] );

			return null;
		}

		$payload = $task->getPayload();
		if ( RetranslationTask::PHASE_WPML_SYNCING !== ( $payload['phase'] ?? null ) ) {
			JobLog::addRetranslationEvent( 'retranslation_task_sweep', [
				'result' => 'not_syncing',
				'task'   => $this->getTaskLogData( $task ),
			] );

			return $task;
		}

		$ids       = isset( $payload['wpml_job_ids'] ) ? array_map( 'intval', (array) $payload['wpml_job_ids'] ) : [];
		$completed = isset( $payload['wpml_completed_job_ids'] ) ? array_map( 'intval', (array) $payload['wpml_completed_job_ids'] ) : [];
		$remaining = array_values( array_diff( $ids, $completed ) );
		$createdAt = isset( $payload['sync_started_at'] ) ? (int) $payload['sync_started_at'] : ( isset( $payload['created_at'] ) ? (int) $payload['created_at'] : 0 );
		$timedOut  = $createdAt && time() - $createdAt > self::SYNC_TIMEOUT;

		if ( ! $remaining ) {
			$this->finish( $task );

			JobLog::addRetranslationEvent( 'retranslation_task_sweep', [
				'result'          => 'completed_all_wpml_jobs',
				'wpml_job_count'  => count( $ids ),
				'completed_count' => count( $completed ),
				'timed_out'       => false,
				'task'            => $this->getTaskLogData( $task ),
			] );

			return $task;
		}

		if ( $timedOut ) {
			$this->finishAtCurrentCount( $task );

			JobLog::addRetranslationEvent( 'retranslation_task_sweep', [
				'result'          => 'completed_after_sync_timeout',
				'wpml_job_count'  => count( $ids ),
				'completed_count' => count( $completed ),
				'remaining_count' => count( $remaining ),
				'timed_out'       => true,
				'age'             => time() - $createdAt,
				'task'            => $this->getTaskLogData( $task ),
			] );

			return $task;
		}

		JobLog::addRetranslationEvent( 'retranslation_task_sweep', [
			'result'          => 'waiting_for_downloaded_jobs',
			'wpml_job_count'  => count( $ids ),
			'completed_count' => count( $completed ),
			'remaining_count' => count( $remaining ),
			'timed_out'       => false,
			'age'             => $createdAt ? time() - $createdAt : null,
			'task'            => $this->getTaskLogData( $task ),
		] );

		return $task;
	}

	private function getTaskLogData( $task ) {
		if ( ! $task ) {
			return null;
		}

		$payload         = $task->getPayload();
		$wpmlJobIds      = isset( $payload['wpml_job_ids'] ) ? (array) $payload['wpml_job_ids'] : [];
		$wpmlCompleted   = isset( $payload['wpml_completed_job_ids'] ) ? (array) $payload['wpml_completed_job_ids'] : [];
		$phase           = isset( $payload['phase'] ) ? $payload['phase'] : null;
		$requestId       = ! empty( $payload['request_id'] ) ? (int) $payload['request_id'] : null;

		return [
			'task_id'              => (int) $task->getTaskId(),
			'status'               => (int) $task->getStatus(),
			'status_name'          => $task->getStatusName(),
			'phase'                => $phase,
			'request_id'           => $requestId,
			'ate_job_count'        => isset( $payload['ate_job_count'] ) ? $payload['ate_job_count'] : null,
			'ate_processed_count'  => isset( $payload['ate_processed_job_count'] ) ? $payload['ate_processed_job_count'] : null,
			'suggestions_count'    => isset( $payload['suggestions_count'] ) ? $payload['suggestions_count'] : null,
			'wpml_job_count'       => count( $wpmlJobIds ),
			'wpml_completed_count' => count( $wpmlCompleted ),
			'total_count'          => (int) $task->getTotalCount(),
			'completed_count'      => (int) $task->getCompletedCount(),
			'sync_started_at'      => isset( $payload['sync_started_at'] ) ? (int) $payload['sync_started_at'] : null,
		];
	}

	public function update( BackgroundTask $task ) {
		$this->updateCommand->runUpdate( $task );
	}

	public function delete( BackgroundTask $task ) {
		$this->deleteCommand->run( $task->getTaskId() );
	}

	public function finish( BackgroundTask $task ) {
		$task->finish();
		$this->update( $task );
	}

	public function finishAtCurrentCount( BackgroundTask $task ) {
		$task->setStatus( BackgroundTask::TASK_STATUS_COMPLETED );
		$this->update( $task );
	}
}
