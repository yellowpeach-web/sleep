<?php

namespace WPML\EditHistory;

class KeyResult {

	private $key;

	private $reason;

	private function __construct( $key, $reason ) {
		$this->key    = $key;
		$this->reason = $reason;
	}

	public static function key( array $key ): self {
		return new self( $key, null );
	}

	public static function none( string $reason ): self {
		return new self( null, $reason );
	}

	public function value() {
		return $this->key;
	}

	public function reason() {
		return $this->reason;
	}
}
