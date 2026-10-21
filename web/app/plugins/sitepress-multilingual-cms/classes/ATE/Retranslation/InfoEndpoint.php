<?php

namespace WPML\TM\ATE\Retranslation;

use WPML\Ajax\IHandler;
use WPML\BackgroundTask\BackgroundTaskViewModel;
use WPML\Collect\Support\Collection;
use WPML\Core\BackgroundTask\Model\BackgroundTask;
use WPML\FP\Either;
use WPML\LIB\WP\User;
use WPML\TM\ATE\Retranslation\BackgroundTask\RetranslationTask;
use WPML\TM\ATE\Retranslation\BackgroundTask\TaskManager;
use WPML\TM\Jobs\JobLog;

class InfoEndpoint implements IHandler {

	const POLL_INTERVAL      = 30;
	const SLOW_POLL_INTERVAL = 120;
	const SLOW_AFTER         = 600;
	const PREPARING_TIMEOUT  = 86400;
	const FINALIZING_TIMEOUT = 1800;
	const MAX_FAILURES       = 5;
	const SYNC_POLL_INTERVAL = 10;

	private $taskManager;

	private $infoClient;

	public function __construct( TaskManager $taskManager, InfoClient $infoClient ) {
		$this->taskManager = $taskManager;
		$this->infoClient  = $infoClient;
	}

	public function run( Collection $data ) {
		if ( ! User::canManageTranslations() && ! current_user_can( 'manage_options' ) ) {
			return Either::left( 'Forbidden' );
		}

		$task = $this->taskManager->sweepRetranslationTask();
		JobLog::addRetranslationEvent( 'retranslation_info_poll_task', [
			'task_found' => (bool) $task,
			'task'       => $this->getTaskLogData( $task ),
		] );

		$result = $this->handleTask( $task );

		return Either::of( [
			'task'     => $result['task'],
			'polling'  => $result['polling'],
			'interval' => $result['polling'] ? $result['interval'] : 0,
		] );
	}

	private function handleTask( $task ) {
		if ( ! $task || $task->isStatusCompleted() ) {
			JobLog::addRetranslationEvent( 'retranslation_info_poll_result', [
				'result' => $task ? 'task_completed' : 'no_task',
				'task'   => $this->getTaskLogData( $task ),
			] );

			return $this->stopResponse( $task ? BackgroundTaskViewModel::get( $task ) : null );
		}

		$payload = $task->getPayload();
		$phase   = isset( $payload['phase'] ) ? $payload['phase'] : RetranslationTask::PHASE_PREPARING;

		if ( RetranslationTask::PHASE_WPML_SYNCING === $phase ) {
			JobLog::addRetranslationEvent( 'retranslation_info_poll_result', [
				'result'       => 'wpml_syncing_no_ate_call',
				'phase_before' => $phase,
				'request_id'   => ! empty( $payload['request_id'] ) ? (int) $payload['request_id'] : null,
				'interval'     => self::SYNC_POLL_INTERVAL,
				'task'         => $this->getTaskLogData( $task ),
			] );

			return $this->continueResponse( $task, self::SYNC_POLL_INTERVAL );
		}

		$requestId = ! empty( $payload['request_id'] ) ? (int) $payload['request_id'] : null;
		$info      = $this->infoClient->get( $requestId )->getOrElse( null );

		return $info ? $this->handleInfo( $task, $info, $phase, $requestId ) : $this->handleFailure( $task, $phase, $requestId );
	}

	private function handleInfo( BackgroundTask $task, RetranslationInfo $info, $phaseBefore = null, $requestId = null ) {
		$infoSummary = $this->getInfoLogData( $info );

		if ( ! $info->isFound() ) {
			JobLog::addRetranslationEvent( 'retranslation_info_poll_result', [
				'result'       => 'ate_not_found',
				'phase_before' => $phaseBefore,
				'request_id'   => $requestId,
				'ate'          => $infoSummary,
				'task_before'  => $this->getTaskLogData( $task ),
			] );

			return $this->handleNotFound( $task );
		}

		if ( $info->isCanceled() ) {
			$this->taskManager->delete( $task );

			JobLog::addRetranslationEvent( 'retranslation_info_poll_result', [
				'result'       => 'ate_canceled_task_deleted',
				'phase_before' => $phaseBefore,
				'request_id'   => $requestId,
				'ate'          => $infoSummary,
				'task_before'  => $this->getTaskLogData( $task ),
				'task_after'   => null,
			] );

			return $this->stopResponse( null );
		}

		$task = $this->taskManager->updateFromAteInfo( $info );
		if ( ! $task ) {
			JobLog::addRetranslationEvent( 'retranslation_info_poll_result', [
				'result'       => 'ate_found_no_task_after_update',
				'phase_before' => $phaseBefore,
				'request_id'   => $requestId,
				'ate'          => $infoSummary,
				'task_after'   => null,
			] );

			return $this->stopResponse( null );
		}

		JobLog::addRetranslationEvent( 'retranslation_info_poll_result', [
			'result'       => 'ate_found_task_updated',
			'phase_before' => $phaseBefore,
			'phase_after'  => $this->getTaskPhase( $task ),
			'request_id'   => $requestId,
			'ate'          => $infoSummary,
			'task_after'   => $this->getTaskLogData( $task ),
		] );

		return $this->continueResponse(
			$task,
			$info->isCompleted() ? self::SYNC_POLL_INTERVAL : self::POLL_INTERVAL
		);
	}

	private function handleNotFound( BackgroundTask $task ) {
		$payload = $task->getPayload();

		if ( ! empty( $payload['request_id'] ) ) {
			$this->taskManager->delete( $task );

			JobLog::addRetranslationEvent( 'retranslation_info_not_found_handled', [
				'result' => 'deleted_task_with_request_id',
				'task'   => $this->getTaskLogData( $task ),
			] );

			return $this->stopResponse( null );
		}

		$age = time() - ( isset( $payload['created_at'] ) ? (int) $payload['created_at'] : time() );
		if ( $age > self::PREPARING_TIMEOUT ) {
			$this->taskManager->delete( $task );

			JobLog::addRetranslationEvent( 'retranslation_info_not_found_handled', [
				'result' => 'deleted_preparing_timeout',
				'age'    => $age,
				'task'   => $this->getTaskLogData( $task ),
			] );

			return $this->stopResponse( null );
		}

		JobLog::addRetranslationEvent( 'retranslation_info_not_found_handled', [
			'result'   => 'keep_polling',
			'age'      => $age,
			'interval' => $age > self::SLOW_AFTER ? self::SLOW_POLL_INTERVAL : self::POLL_INTERVAL,
			'task'     => $this->getTaskLogData( $task ),
		] );

		return $this->continueResponse(
			$task,
			$age > self::SLOW_AFTER ? self::SLOW_POLL_INTERVAL : self::POLL_INTERVAL
		);
	}

	private function handleFailure( BackgroundTask $task, $phaseBefore = null, $requestId = null ) {
		$payload             = $task->getPayload();
		$payload['failures'] = ( isset( $payload['failures'] ) ? (int) $payload['failures'] : 0 ) + 1;

		if ( $payload['failures'] >= self::MAX_FAILURES ) {
			$this->taskManager->delete( $task );

			JobLog::addRetranslationEvent( 'retranslation_info_poll_result', [
				'result'       => 'ate_info_failed_task_deleted',
				'phase_before' => $phaseBefore,
				'request_id'   => $requestId,
				'failures'     => $payload['failures'],
				'task_before'  => $this->getTaskLogData( $task ),
			] );

			return $this->stopResponse( null );
		}

		$task->setPayload( $payload );
		$this->taskManager->update( $task );

		JobLog::addRetranslationEvent( 'retranslation_info_poll_result', [
			'result'       => 'ate_info_failed_keep_polling',
			'phase_before' => $phaseBefore,
			'phase_after'  => $this->getTaskPhase( $task ),
			'request_id'   => $requestId,
			'failures'     => $payload['failures'],
			'task_after'   => $this->getTaskLogData( $task ),
		] );

		return $this->continueResponse( $task, self::POLL_INTERVAL );
	}

	private function continueResponse( BackgroundTask $task, $interval ) {
		$payload = $task->getPayload();
		if (
			RetranslationTask::PHASE_FINALIZING === ( $payload['phase'] ?? null )
			&& ! empty( $payload['finalizing_since'] )
			&& time() - (int) $payload['finalizing_since'] > self::FINALIZING_TIMEOUT
		) {
			$this->taskManager->finish( $task );

			JobLog::addRetranslationEvent( 'retranslation_info_poll_result', [
				'result' => 'completed_after_finalizing_timeout',
				'age'    => time() - (int) $payload['finalizing_since'],
				'task'   => $this->getTaskLogData( $task ),
			] );

			return $this->stopResponse( BackgroundTaskViewModel::get( $task ) );
		}

		return [
			'task'     => BackgroundTaskViewModel::get( $task ),
			'polling'  => true,
			'interval' => $interval,
		];
	}

	private function stopResponse( $taskViewModel ) {
		return [
			'task'     => $taskViewModel,
			'polling'  => false,
			'interval' => 0,
		];
	}

	private function getTaskLogData( $task ) {
		if ( ! $task ) {
			return null;
		}

		$payload       = $task->getPayload();
		$wpmlJobIds    = isset( $payload['wpml_job_ids'] ) ? (array) $payload['wpml_job_ids'] : [];
		$wpmlCompleted = isset( $payload['wpml_completed_job_ids'] ) ? (array) $payload['wpml_completed_job_ids'] : [];

		return [
			'task_id'              => (int) $task->getTaskId(),
			'status'               => (int) $task->getStatus(),
			'phase'                => $this->getTaskPhase( $task ),
			'request_id'           => ! empty( $payload['request_id'] ) ? (int) $payload['request_id'] : null,
			'ate_job_count'        => isset( $payload['ate_job_count'] ) ? $payload['ate_job_count'] : null,
			'ate_processed_count'  => isset( $payload['ate_processed_job_count'] ) ? $payload['ate_processed_job_count'] : null,
			'suggestions_count'    => isset( $payload['suggestions_count'] ) ? $payload['suggestions_count'] : null,
			'wpml_job_count'       => count( $wpmlJobIds ),
			'wpml_completed_count' => count( $wpmlCompleted ),
			'total_count'          => (int) $task->getTotalCount(),
			'completed_count'      => (int) $task->getCompletedCount(),
		];
	}

	private function getTaskPhase( BackgroundTask $task ) {
		$payload = $task->getPayload();

		return isset( $payload['phase'] ) ? $payload['phase'] : null;
	}

	private function getInfoLogData( RetranslationInfo $info ) {
		return [
			'found'                 => $info->isFound(),
			'in_progress'           => $info->isInProgress(),
			'completed'             => $info->isCompleted(),
			'request_id'            => $info->getRequestId(),
			'cw_request_id'         => $info->getRequestId(),
			'job_count'             => $info->getJobCount(),
			'processed_job_count'   => $info->getProcessedJobCount(),
			'suggestions_count'     => $info->getSuggestionsCount(),
		];
	}
}
