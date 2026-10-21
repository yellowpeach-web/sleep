<?php

class WPML_Links_Fixed_Status_For_Posts extends WPML_Links_Fixed_Status {

	private $translation_id;
	private $wpdb;

	public function __construct( $wpdb, $element_id, $element_type ) {
		$this->wpdb = $wpdb;

		$this->translation_id = $wpdb->get_var( $wpdb->prepare( "SELECT translation_id
														 FROM {$wpdb->prefix}icl_translations
														 WHERE element_id=%d
														 AND element_type=%s",
														 $element_id,
														 $element_type ) );
	}

	public function set( $status ) {
		$status = $status ? 1 : 0;
		$wpdb   = $this->wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_translation_status SET links_fixed=%d WHERE translation_id=%d",
				$status,
				$this->translation_id
			)
		);
	}

	public function are_links_fixed() {
		$wpdb = $this->wpdb;

		$state = $wpdb->get_var( $wpdb->prepare( "SELECT links_fixed
														FROM {$wpdb->prefix}icl_translation_status
														WHERE translation_id=%d",
														$this->translation_id ) );
		return (bool) $state;
	}

	public static function clear( $element_id, $element_type ) {
		global $wpdb;
		$status = new WPML_Links_Fixed_Status_For_Posts( $wpdb, $element_id, $element_type );
		$status->set( false );
	}
}
