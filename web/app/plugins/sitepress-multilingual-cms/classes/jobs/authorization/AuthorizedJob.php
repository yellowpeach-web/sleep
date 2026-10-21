<?php

namespace WPML\TM\Jobs\Authorization;

final class AuthorizedJob {

	private $localId;

	private $ateId;

	private $type;

	private $access;

	public function __construct( $localId, $ateId, $type, $access ) {
		$this->localId = (int) $localId;
		$this->ateId   = (int) $ateId;
		$this->type    = $type;
		$this->access  = (string) $access;
	}

	public function localId() {
		return $this->localId;
	}

	public function ateId() {
		return $this->ateId;
	}

	public function type() {
		return $this->type;
	}

	public function access() {
		return $this->access;
	}
}
