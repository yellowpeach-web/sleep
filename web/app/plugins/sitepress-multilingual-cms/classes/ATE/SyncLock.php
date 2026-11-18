<?php

namespace WPML\TM\ATE;

use WPML\Utilities\KeyedLock;
use function WPML\Container\make;

class SyncLock {
	const TTL = 30;

	private $keyedLock;

	public function __construct() {
		$this->keyedLock = make( KeyedLock::class, [ ':name' => 'ate_sync' ] )->forgetRefusals();
	}

	public function create( $key = null ) {
		return $this->keyedLock->create( $key, self::TTL );
	}

	public function release() {
		return $this->keyedLock->release();
	}
}
