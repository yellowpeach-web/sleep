<?php

class WPML_Term_Non_Translatable_Adjust_Count {

	private $sitepress;

	private $wpdb;

	private $taxonomies_non_translatable = null;

	private $taxonomies_signature = null;

	private $adjusted_counts = array();

	public function __construct( SitePress $sitepress, wpdb $wpdb ) {
		$this->sitepress = $sitepress;
		$this->wpdb      = $wpdb;

		add_filter( 'get_term', [ $this, 'get_term_adjust_count' ], 10, 2 );
	}

	public function get_term_adjust_count( $term, $taxonomy = null ) {
		if ( ! is_object( $term ) || ! isset( $term->taxonomy, $term->term_taxonomy_id ) ) {
			return $term;
		}

		if ( (int) $term->count <= 0 ) {
			return $term;
		}

		$lang = $this->sitepress->get_current_language();
		if ( ! $lang || 'all' === $lang ) {
			return $term;
		}

		if ( ! in_array( (string) $term->taxonomy, $this->get_non_translatable_taxonomies(), true ) ) {
			return $term;
		}

		$adjusted = $this->get_adjusted_count( $term, $lang );

		if ( $adjusted < (int) $term->count ) {
			$term->count = $adjusted;
		}

		return $term;
	}

	private function get_non_translatable_taxonomies() {
		global $wp_taxonomies;

		$signature = count( (array) $wp_taxonomies );
		if ( null !== $this->taxonomies_non_translatable && $signature === $this->taxonomies_signature ) {
			return $this->taxonomies_non_translatable;
		}

		$this->taxonomies_signature        = $signature;
		$this->taxonomies_non_translatable = array();

		foreach ( (array) $wp_taxonomies as $name => $taxonomy ) {
			if ( $this->sitepress->is_translated_taxonomy( $name ) ) {
				continue;
			}
			foreach ( (array) $taxonomy->object_type as $object_type ) {
				if ( $this->sitepress->is_translated_post_type( $object_type ) ) {
					$this->taxonomies_non_translatable[] = (string) $name;
					break;
				}
			}
		}

		return $this->taxonomies_non_translatable;
	}

	private function get_adjusted_count( $term, $lang ) {
		$taxonomy = (string) $term->taxonomy;
		$ttid     = (int) $term->term_taxonomy_id;

		if ( isset( $this->adjusted_counts[ $taxonomy ][ $ttid ] ) ) {
			return $this->adjusted_counts[ $taxonomy ][ $ttid ];
		}

		$table_prefix = $this->wpdb->prefix;

		$count = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*)
				 FROM {$table_prefix}term_relationships tr
				 INNER JOIN {$table_prefix}posts p ON p.ID = tr.object_id
				 LEFT JOIN {$table_prefix}icl_translations it
				         ON it.element_id = p.ID
				        AND it.element_type = CONCAT( 'post_', p.post_type )
				 WHERE tr.term_taxonomy_id = %d
				   AND p.post_status = 'publish'
				   AND ( it.language_code = %s OR it.language_code IS NULL )",
				$ttid,
				$lang
			)
		);

		$this->adjusted_counts[ $taxonomy ][ $ttid ] = $count;

		return $count;
	}
}
