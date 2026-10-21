<?php

class WPML_Flags_Factory {
	private $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function create() {
		return new WPML_Flags( $this->wpdb, new icl_cache( WPML_Flags::CACHE_NAME, true ) );
	}
}
