<?php

namespace WPML\TM\Jobs\EditAccess;

class AutomaticTranslationPermission {

	const FILTER = 'wpml_user_can_use_automatic_translation';

	public static function isAllowedForCurrentUser() {
		return self::isAllowedFor( wp_get_current_user() );
	}

	public static function isAllowedFor( \WP_User $user ) {
		return (bool) apply_filters( self::FILTER, true, $user );
	}
}
