<?php

class WPML_Multilingual_Options_Utils {
	private $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function get_option_without_filtering( $option_name, $default = null ) {
		$wpdb  = $this->wpdb;
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value
				FROM {$wpdb->options}
				WHERE option_name = %s
				LIMIT 1",
				$option_name
			)
		);

		return $value ? maybe_unserialize( $value ) : $default;
	}
}
