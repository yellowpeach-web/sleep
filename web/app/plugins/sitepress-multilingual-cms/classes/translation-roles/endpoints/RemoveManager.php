<?php

namespace WPML\TranslationRoles;

use WPML\LIB\WP\User;

class RemoveManager extends Remove {

	protected static function getCap() {
		return User::CAP_MANAGE_TRANSLATIONS;
	}
}
