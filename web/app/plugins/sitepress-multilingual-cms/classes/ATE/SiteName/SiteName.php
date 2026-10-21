<?php

namespace WPML\TM\ATE\SiteName;

class SiteName {

	public static function read() {
		global $wpdb;

		$alloptions = wp_load_alloptions();

		if ( isset( $alloptions['blogname'] ) ) {
			$value = $alloptions['blogname'];
		} else {
			$value = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
					'blogname'
				)
			);
		}

		return null === $value ? '' : wp_specialchars_decode( (string) $value, ENT_QUOTES );
	}
}
