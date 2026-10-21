<?php

use WPML\TaxonomyTermTranslation\Hooks as TermTranslationHooks;

class WPML_Term_Adjust_Id {

	private $debug_backtrace;

	private $term_translation;

	private $post_translation;

	private $sitepress;

	private $opt_out;

	private $translated_ttids = [];

	public function __construct(
		WPML_Debug_BackTrace $debug_backtrace,
		WPML_Term_Translation $term_translation,
		WPML_Post_Translation $post_translation,
		SitePress $sitepress,
		?WPML_Term_Query_Opt_Out $opt_out = null
	) {
		$this->debug_backtrace  = $debug_backtrace;
		$this->term_translation = $term_translation;
		$this->post_translation = $post_translation;
		$this->sitepress        = $sitepress;
		$this->opt_out          = $opt_out ? $opt_out : new WPML_Term_Query_Opt_Out( $sitepress );
	}

	public function filter(
		WP_Term $term,
		$adjust_id_url_filter_off
	) {
		if (
			$adjust_id_url_filter_off
			|| $this->opt_out->is_id_adjustment_opted_out()
		) {
			WPML_Non_Persistent_Cache::flush_group( __CLASS__ );

			return $term;
		}

		if ( $this->is_known_without_adjustment( (int) $term->term_taxonomy_id, $this->sitepress->get_current_language() ) ) {
			return clone $term;
		}

		if (
			$this->is_ajax_add_term_translation()
			|| $this->is_populated_for_a_query_skipping_filters()
			|| $this->debug_backtrace->are_functions_in_call_stack(
				[
					'get_category_parents',
					'get_permalink',
					'wp_update_post',
					'wp_update_term',
					'wp_delete_term',
					'wp_set_object_terms',
					'wp_update_nav_menu_object',
				],
				false
			)
		) {
			WPML_Non_Persistent_Cache::flush_group( __CLASS__ );

			return $term;
		}

		$object_id = isset( $term->object_id ) ? $term->object_id : false;

		$key         = md5( (int) $object_id . $term->term_id . $this->sitepress->get_current_language() . $term->count );
		$found       = false;
		$cached_term = WPML_Non_Persistent_Cache::get( $key, __CLASS__, $found );
		if ( $found ) {
            return is_object( $cached_term ) ? clone $cached_term : $cached_term;
		}

		$translated_id = $this->translated_term_taxonomy_id( (int) $term->term_taxonomy_id, $this->sitepress->get_current_language() );

		if ( $translated_id && (int) $translated_id !== (int) $term->term_taxonomy_id ) {

			if ( has_filter( 'get_term', [ $this->sitepress, 'get_term_adjust_id' ] ) ) {
				$should_add_filter = remove_filter( 'get_term', [ $this->sitepress, 'get_term_adjust_id' ], 1 );
			}

			$term = get_term_by( 'term_taxonomy_id', $translated_id, $term->taxonomy );

			if ( ! empty( $should_add_filter ) ) {
				add_filter( 'get_term', [ $this->sitepress, 'get_term_adjust_id' ], 1, 1 );
			}

			if ( $object_id ) {
				$translated_object_id = $this->post_translation->element_id_in( $object_id, $this->sitepress->get_current_language() );
				if ( $translated_object_id ) {
					$term->object_id = $translated_object_id;
				} elseif ( $this->sitepress->is_display_as_translated_post_type( $this->post_translation->get_type( $object_id ) ) ) {
					$term->object_id = $this->post_translation->element_id_in( $object_id, $this->sitepress->get_default_language() );
				}
			}
		}

		WPML_Non_Persistent_Cache::set( $key, $term, __CLASS__ );

        return is_object( $term ) ? clone $term : $term;
	}

	public function forget_translations() {
		$this->translated_ttids = [];
	}

	private function translated_term_taxonomy_id( $term_taxonomy_id, $language ) {
		if ( ! isset( $this->translated_ttids[ $language ] ) || ! array_key_exists( $term_taxonomy_id, $this->translated_ttids[ $language ] ) ) {
			$translated = $this->term_translation->element_id_in( $term_taxonomy_id, $language );

			$this->translated_ttids[ $language ][ $term_taxonomy_id ] = $translated ? (int) $translated : null;
		}

		return $this->translated_ttids[ $language ][ $term_taxonomy_id ];
	}

	private function is_known_without_adjustment( $term_taxonomy_id, $language ) {
		if ( ! isset( $this->translated_ttids[ $language ] ) || ! array_key_exists( $term_taxonomy_id, $this->translated_ttids[ $language ] ) ) {
			return false;
		}
		$translated = $this->translated_ttids[ $language ][ $term_taxonomy_id ];

		return ! $translated || $translated === $term_taxonomy_id;
	}

	private function is_populated_for_a_query_skipping_filters() {
		foreach ( (array) $this->debug_backtrace->get_backtrace() as $frame ) {
			if ( isset( $frame['object'] ) && $frame['object'] instanceof WP_Term_Query ) {
				return TermTranslationHooks::shouldSkip( $frame['object']->query_vars );
			}
		}

		return false;
	}

	private function is_ajax_add_term_translation() {
		$taxonomy = isset( $_POST['taxonomy'] ) ? $_POST['taxonomy'] : false;
		if ( $taxonomy ) {
			return isset( $_POST['action'] ) && 'add-tag' === $_POST['action'] && ! empty( $_POST[ 'icl_tax_' . $taxonomy . '_language' ] );
		}

		return false;
	}
}
