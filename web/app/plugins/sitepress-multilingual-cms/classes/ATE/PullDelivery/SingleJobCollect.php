<?php

namespace WPML\TM\ATE\PullDelivery;

use WPML\TM\API\ATE;
use WPML\TM\ATE\CollectLock;
use WPML\TM\ATE\Receive\SingleJobDelivery;
use WPML\TM\ATE\Receive\TranslationApplier;
use WPML\TM\ATE\SyncLock;
use WPML\TM\Jobs\JobLog;
use function WPML\Container\make;

class SingleJobCollect {

	const COLLECT_LOCK_BUSY = 'collect_lock_busy';

	const LOCK_NAME = 'editorOpenCollect';

	const LOCK_WAIT_TRIES   = 12;
	const LOCK_WAIT_STEP_US = 250000;

	private $collectLock;

	private $deliveryFactory;

	private $lockWaitSleeper = 'usleep';

	public function __construct( ?CollectLock $collectLock = null, ?callable $deliveryFactory = null ) {
		$this->collectLock     = $collectLock ?: make( CollectLock::class );
		$this->deliveryFactory = $deliveryFactory;
	}

	public function setLockWaitSleeper( callable $sleeper ) {
		$this->lockWaitSleeper = $sleeper;

		return $this;
	}

	public function collect( $wpmlJobId ) {
		$wpmlJobId = (int) $wpmlJobId;

		JobLog::maybeInitRequest();
		JobLog::createNewGroup(
			JobLog::GROUP_ID_DOWNLOAD_JOBS,
			'ATE single-job collect (editor open)',
			[ 'jobId' => $wpmlJobId ]
		);

		try {
			$runKey = $this->acquireWithWait();

			if ( ! $runKey ) {
				JobLog::add( 'editor_open_collect_lock_busy', [ 'jobId' => $wpmlJobId ] );

				return self::COLLECT_LOCK_BUSY;
			}

			try {
				$result  = $this->delivery()->deliver( $wpmlJobId, self::LOCK_NAME );
				$outcome = (string) $result['outcome'];

				JobLog::add( 'editor_open_collect', [ 'jobId' => $wpmlJobId, 'outcome' => $outcome ] );

				return $outcome;
			} catch ( \Throwable $e ) {
				JobLog::addError(
					'editor_open_collect_failed',
					[
						'jobId'   => $wpmlJobId,
						'message' => $e->getMessage(),
						'origin'  => $e->getFile() . ':' . $e->getLine(),
					]
				);

				return SingleJobDelivery::APPLY_FAILED;
			} finally {
				$this->collectLock->release();
			}
		} finally {
			JobLog::finishCurrentGroup();
		}
	}

	private function acquireWithWait() {
		$tries = 0;

		while ( true ) {
			$key = $this->collectLock->create();

			if ( $key || ++$tries >= self::LOCK_WAIT_TRIES ) {
				return $key;
			}

			call_user_func( $this->lockWaitSleeper, self::LOCK_WAIT_STEP_US );
		}
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
}
