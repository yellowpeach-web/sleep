<?php

namespace WPML\StringTranslation\Infrastructure\StringCore\Lock;

use WPML\ST\Upgrade\Deferred\Runner;
use WPML\Utilities\AdvisoryLock;
use WPML\Utilities\AdvisoryLockFactory;
use WPML\Utilities\ILock;
use WPML\Utilities\Lock;
use function WPML\Container\make;

class PackTranslationsReloadLock {

	const QUEUE_WORKER_LOCK_NAME = 'processstringsqueue';

	const QUEUE_WORKER_LOCK_TIMEOUT = 60;

	private $advisoryLockFactory;

	private $advisoryLock;

	private $queueWorkerLock;

	public function __construct( AdvisoryLockFactory $advisoryLockFactory ) {
		$this->advisoryLockFactory = $advisoryLockFactory;
	}

	public function acquire(): bool {
		$advisoryLock = $this->advisoryLockFactory->create( Runner::LOCK_NAME );
		if ( ! $advisoryLock->acquire( 0 ) ) {
			return false;
		}

		$queueWorkerLock = $this->makeQueueWorkerLock();
		if ( ! $queueWorkerLock->create( self::QUEUE_WORKER_LOCK_TIMEOUT ) ) {
			$advisoryLock->release();

			return false;
		}

		$this->advisoryLock    = $advisoryLock;
		$this->queueWorkerLock = $queueWorkerLock;

		return true;
	}

	public function release() {
		if ( $this->queueWorkerLock ) {
			$this->queueWorkerLock->release();
			$this->queueWorkerLock = null;
		}

		if ( $this->advisoryLock ) {
			$this->advisoryLock->release();
			$this->advisoryLock = null;
		}
	}

	protected function makeQueueWorkerLock() {
		return make( Lock::class, [ ':name' => self::QUEUE_WORKER_LOCK_NAME ] );
	}
}
