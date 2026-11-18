<?php

namespace WPML\TM\ATE\Cancel;

use WPML\TM\ATE\Handshake\Clock;

class ReplayBudget {

	const SECONDS_PER_ID = 0.4;

	const MARGIN = 2;

	const ROOM = 1.25;

	private $deadline;

	private $secondsPerId = self::SECONDS_PER_ID;

	public function __construct( $deadline ) {
		$this->deadline = (float) $deadline;
	}

	public function nextSize( $max ) {
		return (int) max( 0, min( $max, floor( $this->usable() / ( $this->secondsPerId * self::ROOM ) ) ) );
	}

	public function timeout() {
		return (int) max( 1, floor( $this->deadline - Clock::exact() ) );
	}

	public function now() {
		return Clock::exact();
	}

	public function took( $ids, $seconds ) {
		if ( $ids > 0 && $seconds > 0 ) {
			$this->secondsPerId = $seconds / $ids;
		}
	}

	private function usable() {
		return $this->deadline - Clock::exact() - self::MARGIN;
	}
}
