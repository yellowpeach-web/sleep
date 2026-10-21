<?php

namespace WPML\TM\ATE\Retranslation;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\TM\ATE\Retranslation\BackgroundTask\TaskManager;
use WPML\TM\ATE\SyncLock;
use WPML\TM\Jobs\JobLog;

class Endpoint implements IHandler {
	const KIND_SUGGESTIONS = 'suggestions';

	private $singlePageBatchHandler;

	private $scheduler;

	private $syncLock;

	private $taskManager;

	public function __construct( SinglePageBatchHandler $singlePageBatchHandler, Scheduler $scheduler, SyncLock $syncLock, TaskManager $taskManager ) {
		$this->singlePageBatchHandler = $singlePageBatchHandler;
		$this->scheduler              = $scheduler;
		$this->syncLock               = $syncLock;
		$this->taskManager            = $taskManager;
	}

	public function run( Collection $data ) {
		$firstSchedule = (bool) $data->get( 'firstSchedule', false );
		$kind          = $data->get( 'kind' );
		$requestId     = absint( $data->get( 'retranslation_request_id', 0 ) );
		$shouldCreate  = $firstSchedule && self::KIND_SUGGESTIONS === $kind;

		JobLog::addRetranslationEvent( 'retranslation_endpoint_called', [
			'firstSchedule'                     => $firstSchedule,
			'kind'                              => $kind,
			'retranslation_request_id'          => $requestId ?: null,
			'should_create_task'                => $shouldCreate,
			'last_call'                         => $this->scheduler->lastCall(),
			'page'                              => (int) $data->get( 'page', 1 ),
		] );

		if ( $firstSchedule ) {
			$this->scheduler->scheduleNextRun();

			if ( $shouldCreate ) {
				$this->taskManager->getOrCreateRetranslationTask( $requestId ?: null );
			}

			return Either::of( [] );
		}

		$lockKey = $this->syncLock->create( $data->get( 'lockKey' ) );
		if ( ! $lockKey ) {
			$this->scheduler->scheduleNextRun();
			return Either::left( [ 'lockKey' => false, 'nextPage' => 0 ] );
		}

		$page = $data->get( 'page', 1 );

		$singlePageBatchHandlerResult = $this->singlePageBatchHandler->handle( $page );

		switch ( $singlePageBatchHandlerResult['state'] ) {
			case SinglePageBatchHandler::NOT_FINISHED_IN_ATE:
				$this->scheduler->scheduleNextRun();
				$this->syncLock->release();
				$lockKey = false;
				break;
			case SinglePageBatchHandler::FINISHED_IN_WPML:
				$this->taskManager->completeDrainIfNoJobsToSync();
				$this->scheduler->disable();
				$this->syncLock->release();
				$lockKey = false;
				break;
		}

		return Either::of( [ 'lockKey' => $lockKey, 'nextPage' => $singlePageBatchHandlerResult['nextPage'] ] );
	}
}
