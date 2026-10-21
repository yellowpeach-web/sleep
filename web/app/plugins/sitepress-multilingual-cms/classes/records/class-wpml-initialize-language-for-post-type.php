<?php

class WPML_Initialize_Language_For_Post_Type {

	private $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function run( $post_type, $default_language ) {
		$wpdb = $this->wpdb;

		do {
			$trid_max = (int) $this->wpdb->get_var( "SELECT MAX(trid) FROM {$wpdb->prefix}icl_translations" ) + 1;
			$results  = $wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$wpdb->prefix}icl_translations (`element_type`, `element_id`, `trid`, `language_code`)
					SELECT CONCAT('post_' , p.post_type) as element_type, p.ID as element_id, %d + p.ID as trid, %s as language_code
					FROM {$wpdb->posts} p
					LEFT OUTER JOIN {$wpdb->prefix}icl_translations t
					ON t.element_id = p.ID AND t.element_type = CONCAT('post_', p.post_type)
					WHERE p.post_type = %s AND t.translation_id IS NULL
					LIMIT 500",
					$trid_max,
					$default_language,
					$post_type
				)
			);
		} while ( $results && ! $this->wpdb->last_error );

		do_action( 'wpml_translation_update', [
			'type' => 'initialize_language_for_post_type',
			'post_type' => $post_type,
			'context' => 'post',
		] );

		return $results === 0 && ! $this->wpdb->last_error;
	}
}
