<?php

namespace WPML\Remote;

class UntrustedDestinationException extends \InvalidArgumentException {

	private $reason;

	public function __construct( $reason, $message ) {
		parent::__construct( $message );
		$this->reason = (string) $reason;
	}

	public function reason() {
		return $this->reason;
	}
}
