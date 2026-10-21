<?php

namespace WPML\TM\ATE;

use WPML\Utilities\KeyedLock;
use function WPML\Container\make;

class CollectLock {

	const TIMEOUT_SECONDS = 90;

	private $keyedLock;

	public function __construct() {
		$this->keyedLock = make( KeyedLock::class, [ ':name' => 'ate_collect' ] );
	}

	public function create( $key = null ) {
		return $this->keyedLock->create( $key, self::TIMEOUT_SECONDS );
	}

	public function release() {
		return $this->keyedLock->release();
	}
}
