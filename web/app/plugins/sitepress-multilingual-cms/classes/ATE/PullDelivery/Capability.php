<?php

namespace WPML\TM\ATE\PullDelivery;

class Capability {

	const PING = [ 'manage_options', 'manage_translations', 'translate' ];

	public static function userMayPing() {
		foreach ( self::PING as $capability ) {
			if ( current_user_can( $capability ) ) {
				return true;
			}
		}

		return false;
	}
}
