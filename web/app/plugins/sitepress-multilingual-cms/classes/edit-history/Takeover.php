<?php

namespace WPML\EditHistory;

use WPML\TM\ATE\Review\ReviewStatus;

class Takeover {

	private $resend;

	public function __construct( $resend = null ) {
		$this->resend = $resend ?? static function ( int $rid ) {
			$resend = SendFactory::resend();
			if ( $resend ) {
				$resend->afterTakeover( $rid );
			}
		};
	}

	public function leavesJobRunning( $reviewStatus ): bool {
		return PanelChoice::isActive() || ReviewStatus::EDITING === $reviewStatus;
	}

	public function tookOver( int $rid, bool $automatic ) {
		if ( $automatic ) {
			( $this->resend )( $rid );
		}
	}
}
