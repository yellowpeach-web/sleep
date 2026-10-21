<?php

class WPML_Term_Filters extends WPML_WPDB_And_SP_User {

	public function init() {
		$taxonomies = get_taxonomies();

		foreach ( $taxonomies as $taxonomy ) {
			$this->add_hooks_to_translated_taxonomy( $taxonomy );
		}

		add_action( 'registered_taxonomy', [ $this, 'registered_taxonomy' ], 10, 3 );
	}

	public function registered_taxonomy( $taxonomy, $object_type, $taxonomy_object ) {
		$this->add_hooks_to_translated_taxonomy( $taxonomy );
	}

	private function add_hooks_to_translated_taxonomy( $taxonomy ) {
		if ( is_taxonomy_translated( $taxonomy ) ) {
			add_filter( "pre_option_{$taxonomy}_children", [ $this, 'pre_option_tax_children' ], 10, 0 );
			add_filter( "default_option_{$taxonomy}_children", [ $this, 'default_option_tax_children' ], 10, 1 );
			add_action( "create_{$taxonomy}", [ $this, 'update_tax_children_option' ], 10, 0 );
			add_action( "edit_{$taxonomy}", [ $this, 'update_tax_children_option' ], 10, 0 );
		}
	}

	public function update_tax_children_option( $taxonomy_input = false ) {
		global $wpml_language_resolution, $wp_taxonomies;

		$language_codes   = $wpml_language_resolution->get_active_language_codes();
		$language_codes[] = 'all';
		$taxonomy         = str_replace( array( 'create_', 'edit_' ), '', current_action() );
		$taxonomy         = isset( $wp_taxonomies[ $taxonomy ] ) ? $taxonomy : $taxonomy_input;
		$hierarchies = $this->get_tax_hier_arrays( $taxonomy, $language_codes );
		foreach ( $language_codes as $lang ) {
			$option_key = "{$taxonomy}_children_{$lang}";
			update_option( $option_key, $hierarchies[ $lang ] );
		}
	}

	public function pre_option_tax_children() {
		$taxonomy     = str_replace( array( 'pre_option_', '_children' ), '', current_filter() );
		$lang         = $this->sitepress->get_current_language();
		$option_key   = "{$taxonomy}_children_{$lang}";
		$tax_children = get_option( $option_key, false );
		if ( $tax_children === false ) {
			$tax_children = $this->get_tax_hier_array( $taxonomy, $lang );
			update_option( $option_key, $tax_children );
		}

		return ! empty( $tax_children ) ? $tax_children : false;
	}

	public function default_option_tax_children( $default ) {
		static $building = array();

		$taxonomy = (string) substr( current_filter(), strlen( 'default_option_' ), - strlen( '_children' ) );
		if ( ! empty( $building[ $taxonomy ] ) || ! is_taxonomy_hierarchical( $taxonomy ) ) {
			return $default;
		}

		$building[ $taxonomy ] = true;
		try {
			$children = $this->get_tax_hier_array( $taxonomy, 'all' );
			add_option( "{$taxonomy}_children", $children );
		} finally {
			unset( $building[ $taxonomy ] );
		}

		return $children;
	}

	public function get_tax_hier_array( $taxonomy, $lang_code ) {
		$wpdb = $this->wpdb;

		$hierarchy = array();

		if ( $lang_code != 'all' ) {
			$terms = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT term_id, parent
					 FROM {$wpdb->term_taxonomy} tt
					 JOIN {$wpdb->prefix}icl_translations iclt
					  ON tt.term_taxonomy_id = iclt.element_id
					 WHERE tt.parent > 0
					  AND tt.taxonomy = %s
					  AND iclt.language_code = %s
					  AND iclt.element_type = %s
					 ORDER BY term_id",
					$taxonomy,
					$lang_code,
					'tax_' . $taxonomy
				)
			);
		} else {
			$terms = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT term_id, parent
					 FROM {$wpdb->term_taxonomy} tt
					 WHERE tt.parent > 0
					  AND tt.taxonomy = %s
					 ORDER BY term_id",
					$taxonomy
				)
			);
		}
		foreach ( $terms as $term ) {
			if ( $term->parent > 0 ) {
				$hierarchy[ $term->parent ]   = isset( $hierarchy[ $term->parent ] )
					? $hierarchy[ $term->parent ] : array();
				$hierarchy[ $term->parent ][] = $term->term_id;
			}
		}

		return $hierarchy;
	}

	public function get_tax_hier_arrays( $taxonomy, array $language_codes ) {
		$wpdb = $this->wpdb;

		$hierarchies = array_fill_keys( $language_codes, array() );
		$with_all    = isset( $hierarchies['all'] );
		$in_all      = array();

		$terms = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tt.term_taxonomy_id, tt.term_id, tt.parent, iclt.language_code
				 FROM {$wpdb->term_taxonomy} tt
				 LEFT JOIN {$wpdb->prefix}icl_translations iclt
				  ON tt.term_taxonomy_id = iclt.element_id
				  AND iclt.element_type = %s
				 WHERE tt.parent > 0
				  AND tt.taxonomy = %s
				 ORDER BY tt.term_id",
				'tax_' . $taxonomy,
				$taxonomy
			)
		);

		foreach ( $terms as $term ) {
			if ( $with_all && ! isset( $in_all[ $term->term_taxonomy_id ] ) ) {
				$in_all[ $term->term_taxonomy_id ]     = true;
				$hierarchies['all'][ $term->parent ][] = $term->term_id;
			}

			if ( null !== $term->language_code && 'all' !== $term->language_code && isset( $hierarchies[ $term->language_code ] ) ) {
				$hierarchies[ $term->language_code ][ $term->parent ][] = $term->term_id;
			}
		}

		return $hierarchies;
	}
}
