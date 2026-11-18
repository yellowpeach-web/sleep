<?php

namespace WPML\TM\ATE\Retry;

use WPML\WP\OptionManager;

class Trigger {
	const RETRY_TIMEOUT = 10 * MINUTE_IN_SECONDS;

	const OPTION_GROUP = 'WPML\TM\ATE\Retry';
	const RETRY_LAST = 'last';

	public function isRetryRequired() {
		$retrySync = OptionManager::getOr( 0, self::RETRY_LAST, self::OPTION_GROUP );

		return ( time() - self::RETRY_TIMEOUT ) > $retrySync;
	}

	public function secondsUntilNextRetry() {
		$retrySync = (int) OptionManager::getOr( 0, self::RETRY_LAST, self::OPTION_GROUP );

		return max( 0, $retrySync + self::RETRY_TIMEOUT - time() );
	}

	public function setLastRetry( $time ) {
		OptionManager::update( self::RETRY_LAST, self::OPTION_GROUP, $time );
	}
}
