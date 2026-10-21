<?php

namespace WPML\Utilities;

class AdvisoryLockFactory {

	public function create( string $name, int $ttlSeconds = 0 ): AdvisoryLock {
		global $wpdb;

		return new AdvisoryLock( $wpdb, $name, $ttlSeconds );
	}
}
