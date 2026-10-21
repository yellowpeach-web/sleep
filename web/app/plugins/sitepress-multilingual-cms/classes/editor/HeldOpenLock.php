<?php

namespace WPML\TM\Editor;

use WPML\Utilities\AdvisoryLock;

class HeldOpenLock {

	private $lock;

	public function __construct( ?AdvisoryLock $lock = null ) {
		$this->lock = $lock;
	}

	public function release() {
		if ( ! $this->lock ) {
			return;
		}

		$lock       = $this->lock;
		$this->lock = null;
		$lock->release();
	}

	public function __destruct() {
		$this->release();
	}
}
