<?php

namespace WPML\LanguageEditor\Save\Phase;

class PhaseResult {

	private $processed;

	private $skipped;

	private $retryable;

	private $hardError;

	private $notes;

	public function __construct( $processed = 0, array $skipped = [], $retryable = false, $hardError = null, array $notes = [] ) {
		$this->processed = (int) $processed;
		$this->skipped   = $skipped;
		$this->retryable = (bool) $retryable;
		$this->hardError = $hardError;
		$this->notes     = $notes;
	}

	public static function ok( $processed, array $skipped = [], array $notes = [] ) {
		return new self( $processed, $skipped, false, null, $notes );
	}

	public static function retry() {
		return new self( 0, [], true, null );
	}

	public static function hardFail( $message ) {
		return new self( 0, [], false, $message );
	}

	public function getProcessed() {
		return $this->processed;
	}

	public function getSkipped() {
		return $this->skipped;
	}

	public function isRetryable() {
		return $this->retryable;
	}

	public function hasHardError() {
		return null !== $this->hardError;
	}

	public function getHardError() {
		return $this->hardError;
	}

	public function getNotes() {
		return $this->notes;
	}
}
