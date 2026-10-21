<?php

namespace WPML\TM\ATE\PullDelivery;

use WPML\Core\Security\ExecutionContext\ExecutionContext;
use WPML\Core\Security\ExecutionContext\ExecutionContextHolder;
use WPML\TM\API\ATE;
use WPML\TM\ATE\Receive\SingleJobDelivery;
use WPML\TM\ATE\Receive\TranslationApplier;
use WPML\TM\ATE\SyncLock;
use WPML\TM\Jobs\JobLog;
use function WPML\Container\make;

class ReturnDelivery {

	const LOCK_NAME = 'ateReturn';

	private $pendingJobs;

	private $deliveryFactory;

	private $ateJobs;

	public function __construct( ?PendingJobs $pendingJobs = null, ?callable $deliveryFactory = null, ?\WPML_TM_ATE_Jobs $ateJobs = null ) {
		$this->pendingJobs     = $pendingJobs ?: new PendingJobs();
		$this->deliveryFactory = $deliveryFactory;
		$this->ateJobs         = $ateJobs;
	}

	public function deliver( $ateJobId, ?ExecutionContext $context = null ) {
		$ateJobId  = (int) $ateJobId;
		$wpmlJobId = $ateJobId > 0 ? (int) $this->ateJobs()->get_wpml_job_id( $ateJobId ) : 0;

		JobLog::maybeInitRequest();
		JobLog::createNewGroup(
			JobLog::GROUP_ID_DOWNLOAD_JOBS,
			'ATE editor return (synchronous delivery)',
			[ 'ate_job_id' => $ateJobId, 'rid' => $wpmlJobId ]
		);

		try {
			$outcome = $wpmlJobId > 0
				? $this->deliverJob( $wpmlJobId, $context )
				: SingleJobDelivery::JOB_MISSING;

			JobLog::add(
				'return_delivery',
				[
					'ate_job_id' => $ateJobId,
					'rid'        => $wpmlJobId,
					'outcome'    => $outcome,
				]
			);
		} catch ( \Throwable $e ) {
			$outcome = SingleJobDelivery::APPLY_FAILED;

			JobLog::addError(
				'return_delivery_uncaught_exception',
				[
					'ate_job_id' => $ateJobId,
					'rid'        => $wpmlJobId,
					'message'    => $e->getMessage(),
					'origin'     => $e->getFile() . ':' . $e->getLine(),
				]
			);
		} finally {
			JobLog::finishCurrentGroup();
		}

		$state = State::onReturnDelivery( $ateJobId, $outcome, $this->pendingJobs->summary() );

		if ( SingleJobDelivery::isRetryable( $outcome ) ) {
			JobLog::maybeInitRequest();
			JobLog::createNewGroup( JobLog::GROUP_ID_DOWNLOAD_JOBS, 'ATE editor return (fallback armed)' );
			JobLog::add(
				'return_delivery_fallback_armed',
				[
					'ate_job_id'  => $ateJobId,
					'outcome'     => $outcome,
					'mode'        => $state['mode'],
					'next_due_at' => $state['next_due_at'],
				]
			);
			JobLog::finishCurrentGroup();
		}

		return $outcome;
	}

	private function deliverJob( $wpmlJobId, ?ExecutionContext $context ) {
		$deliver = function () use ( $wpmlJobId ) {
			return $this->delivery()->deliver( $wpmlJobId, self::LOCK_NAME );
		};

		$result = $context ? ExecutionContextHolder::within( $context, $deliver ) : $deliver();

		return $result['outcome'];
	}

	private function delivery() {
		if ( $this->deliveryFactory ) {
			return call_user_func( $this->deliveryFactory );
		}

		return new SingleJobDelivery(
			make( SyncLock::class ),
			function () {
				return new TranslationApplier( make( ATE::class ) );
			}
		);
	}

	private function ateJobs() {
		if ( null === $this->ateJobs ) {
			$this->ateJobs = make( \WPML_TM_ATE_Jobs::class );
		}

		return $this->ateJobs;
	}
}
