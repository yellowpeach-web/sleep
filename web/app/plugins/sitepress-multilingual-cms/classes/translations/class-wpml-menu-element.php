<?php

class WPML_Menu_Element extends WPML_Term_Element {

	public function __construct( $id, SitePress $sitepress, ?WPML_WP_Cache $wpml_cache = null ) {
		$this->taxonomy = 'nav_menu';
		parent::__construct( $id, $sitepress, $this->taxonomy, $wpml_cache );
	}

	public function get_wp_object() {
		$has_filter = remove_filter( 'get_term', array( $this->sitepress, 'get_term_adjust_id' ), 1 );

		$term = $this->term_or_null( get_term( $this->id, $this->taxonomy ) );
		if ( ! $term ) {
			$term = $this->term_or_null( get_term_by( 'term_taxonomy_id', $this->id, $this->taxonomy ) );
			$term = $term && $this->taxonomy === $term->taxonomy ? $term : null;
		}

		if ( $has_filter ) {
			add_filter( 'get_term', array( $this->sitepress, 'get_term_adjust_id' ), 1, 1 );
		}

		return $term;
	}

	public function get_new_instance( $element_data ) {
		return new WPML_Menu_Element( $element_data->term_id, $this->sitepress, $this->wpml_cache );
	}
}
