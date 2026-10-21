<?php

namespace WPML\OperationRecord;

class UndoWindow {

	const DEFAULT_DAYS = 30;

	const SECONDS_PER_DAY = 86400;

	private $days;

	public function __construct( $days = null ) {
		$this->days = null === $days ? null : (int) $days;
	}

	public function days() {
		if ( null !== $this->days ) {
			return $this->days > 0 ? $this->days : 0;
		}

		$days = defined( 'EMPTY_TRASH_DAYS' ) ? (int) EMPTY_TRASH_DAYS : self::DEFAULT_DAYS;

		return $days > 0 ? $days : 0;
	}

	public function seconds() {
		return $this->days() * self::SECONDS_PER_DAY;
	}

	public function offered() {
		return $this->seconds() > 0;
	}

	public function until( $started ) {
		$started = (int) $started;
		$window  = $this->seconds();

		if ( $started <= 0 || $window <= 0 ) {
			return null;
		}

		return $started + $window;
	}
}
