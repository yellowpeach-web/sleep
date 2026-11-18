<?php

namespace WPML\TM\Upgrade\Commands;

class ServicesRefreshBackoff {
	const TRANSIENT = 'wpml_tm_services_refresh_backoff';

	public static function attempt( callable $refresh ): bool {
		if ( get_transient( self::TRANSIENT ) ) {
			return false;
		}

		set_transient( self::TRANSIENT, time(), DAY_IN_SECONDS );

		$ok = (bool) $refresh();
		if ( $ok ) {
			delete_transient( self::TRANSIENT );
		}

		return $ok;
	}
}
