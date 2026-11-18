<?php

namespace WPML\TM\ATE\Sync;

class ManualSyncBackoff {

	const OPTION   = 'wpml_ate_manual_sync_backoff_at';
	const INTERVAL = 86400;

	private $now;

	public function __construct( $now = null ) {
		$this->now = is_callable( $now ) ? $now : 'time';
	}

	public function isDue() {
		$last = (int) get_option( self::OPTION, 0 );

		return ( (int) call_user_func( $this->now ) - $last ) >= self::INTERVAL;
	}

	public function markSynced() {
		update_option( self::OPTION, (int) call_user_func( $this->now ), false );
	}
}
