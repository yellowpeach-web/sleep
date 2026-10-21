<?php

class WPML_Language_Where_Clause {

	private $sitepress;
	private $wpdb;
	private $display_as_translated_query;

	public function __construct( SitePress $sitepress, wpdb $wpdb, WPML_Display_As_Translated_Posts_Query $display_as_translated_query ) {
		$this->sitepress                   = $sitepress;
		$this->wpdb                        = $wpdb;
		$this->display_as_translated_query = $display_as_translated_query;
	}

	public function get( $post_type ) {

		if ( $this->sitepress->is_translated_post_type( $post_type ) ) {
			$current_language = $this->sitepress->get_current_language();

			if ( $this->sitepress->is_display_as_translated_post_type( $post_type ) ) {
				$default_language              = $this->sitepress->get_default_language();
				$display_as_translated_snippet = $this->display_as_translated_query->get_language_snippet( $current_language, $default_language, array( $post_type ) );
			} else {
				$display_as_translated_snippet = '0';
			}
			$language_snippet = $this->wpdb->prepare( 'language_code = %s', $current_language );

			return " AND ({$language_snippet} OR {$display_as_translated_snippet} )";
		} else {
			return '';
		}
	}

}
