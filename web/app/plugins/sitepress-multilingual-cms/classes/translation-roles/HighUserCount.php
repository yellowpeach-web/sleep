<?php

namespace WPML\TranslationRoles;

class HighUserCount {

	public static function isHigh( $threshold = \WPML_Translation_Roles_Records::HIGH_USER_COUNT_THRESHOLD ) {
		global $wpdb;

		return 1 === (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->users} LIMIT %d,1",
				$threshold
			)
		);
	}
}
