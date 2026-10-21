<?php

namespace WPML\LanguageEditor\ResetDefaults;

class Budget {

	const DEFAULT_SECONDS = 10;

	private $seconds;

	private $startedAt = null;

	public function __construct( $seconds = self::DEFAULT_SECONDS ) {
		$this->seconds = max( 0.0, (float) $seconds );
	}

	public function start() {
		$this->startedAt = $this->now();
	}

	public function exhausted() {
		if ( null === $this->startedAt ) {
			return false;
		}

		return ( $this->now() - $this->startedAt ) >= $this->seconds;
	}

	public function hintMilliseconds() {
		return max( 1, (int) round( $this->seconds * 1000 ) );
	}

	protected function now() {
		return microtime( true );
	}
}
