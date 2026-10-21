<?php

namespace WPML\WP;

class OptionGroupCascade {

	public static function addHooks() {
		add_action( 'deleted_option', [ self::class, 'onDeletedOption' ] );
	}

	public static function onDeletedOption( $option ) {
		if ( ! preg_match( '/^WPML\(([^\/()]+)\)$/', (string) $option, $matches ) ) {
			return;
		}

		global $wpdb;

		$per_key_names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( 'WPML(' . $matches[1] . '/' ) . '%'
			)
		);

		foreach ( $per_key_names as $per_key_name ) {
			delete_option( $per_key_name );
		}
	}
}
