<?php

namespace WPML\TM\ATE\PullDelivery;

class Modes {

	const PUSH_OK     = 'push_ok';
	const PULL_ACTIVE = 'pull_active';
	const PULL_IDLE   = 'pull_idle';
	const SUSPECT     = 'suspect';

	public static function all() {
		return [ self::PUSH_OK, self::PULL_ACTIVE, self::PULL_IDLE, self::SUSPECT ];
	}

	public static function isValid( $mode ) {
		return is_string( $mode ) && in_array( $mode, self::all(), true );
	}

	public static function isPull( $mode ) {
		return self::PULL_ACTIVE === $mode || self::PULL_IDLE === $mode;
	}

	public static function showsDeliveryLine( $mode ) {
		return self::isPull( $mode ) || self::SUSPECT === $mode;
	}
}
