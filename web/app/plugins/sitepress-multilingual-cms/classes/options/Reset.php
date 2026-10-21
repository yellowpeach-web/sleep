<?php

namespace WPML\Options;

class Reset implements \IWPML_Backend_Action {

	public function add_hooks() {
		add_filter( 'wpml_reset_options', [ $this, 'reset_options' ] );
	}

	public function reset_options( $options ) {
		$options[]  = 'WPML_Group_Keys';
		$group_keys = self::get_registered_options();

		return array_merge( $options, $group_keys );
	}

	public static function get_registered_options() {
		global $wpdb;

		if (
			is_object( $wpdb )
			&& isset( $wpdb->options )
			&& method_exists( $wpdb, 'prepare' )
			&& method_exists( $wpdb, 'get_var' )
		) {
			$wpdb->last_error = '';
			$raw              = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
					'WPML_Group_Keys'
				)
			);
			if ( $wpdb->last_error || null === $raw ) {
				return array();
			}

			try {
				if ( is_serialized( $raw ) ) {
					$group_keys = @unserialize( trim( $raw ), array( 'allowed_classes' => false ) );
				} else {
					$group_keys = $raw;
				}
			} catch ( \Throwable $e ) {
				return array();
			}
		} else {
			try {
				$group_keys = get_option( 'WPML_Group_Keys', array() );
			} catch ( \Throwable $e ) {
				return array();
			}
		}

		if ( ! is_array( $group_keys ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$group_keys,
				static function ( $option_name ) {
					return is_string( $option_name )
						&& 191 >= strlen( $option_name )
						&& (bool) preg_match( '/^WPML\([A-Za-z0-9_.:\\\\-]+(?:\/[A-Za-z0-9_.:\\\\-]+)*\)$/', $option_name );
				}
			)
		);
	}
}
