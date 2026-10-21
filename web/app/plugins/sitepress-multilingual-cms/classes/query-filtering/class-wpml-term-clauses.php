<?php

use WPML\TaxonomyTermTranslation\Hooks as TermTranslationHooks;

class WPML_Term_Clauses {

	private $sitepress;

	private $wpdb;

	private $display_as_translated_query;

	private $debug_backtrace;

	private $cache = null;

	private $opt_out;

	public function __construct(
		SitePress $sitepress,
		wpdb $wpdb,
		WPML_Display_As_Translated_Taxonomy_Query $display_as_translated_query,
		WPML_Debug_BackTrace $debug_backtrace,
		?WPML_Term_Query_Opt_Out $opt_out = null
	) {
		$this->sitepress                   = $sitepress;
		$this->wpdb                        = $wpdb;
		$this->display_as_translated_query = $display_as_translated_query;
		$this->debug_backtrace             = $debug_backtrace;
		$this->opt_out                     = $opt_out ? $opt_out : new WPML_Term_Query_Opt_Out( $sitepress );
	}

	public function filter( $clauses, $taxonomies, $args ) {
		if ( $this->opt_out->is_stand_down_requested() ) {
			return $clauses;
		}

		if (
			! $taxonomies
			|| ( class_exists( 'WPML\TaxonomyTermTranslation\Hooks' ) && TermTranslationHooks::shouldSkip( $args ) )
			|| $this->debug_backtrace->are_functions_in_call_stack(
				[
					'_get_term_hierarchy',
					[ 'WPML_Term_Translation_Utils', 'synchronize_terms' ],
					'wp_get_object_terms',
					'get_term_by',
				]
			)
		) {
			return $clauses;
		}

		$icl_taxonomies = array();
		foreach ( $taxonomies as $tax ) {
			if ( $this->sitepress->is_translated_taxonomy( $tax ) ) {
				$icl_taxonomies[] = $tax;
			}
		}

		if ( ! $icl_taxonomies ) {
			return $clauses;
		}

		$icl_taxonomies = "'tax_" . join( "','tax_", esc_sql( $icl_taxonomies ) ) . "'";

		$where_lang = $this->get_where_lang();

		$clauses['join'] .= " LEFT JOIN {$this->wpdb->prefix}icl_translations icl_t
                                    ON icl_t.element_id = tt.term_taxonomy_id
                                        AND icl_t.element_type IN ({$icl_taxonomies})";

		$clauses           = $this->maybe_apply_count_adjustment( $clauses );
		$clauses['where'] .= " AND ( ( icl_t.element_type IN ({$icl_taxonomies}) {$where_lang} )
                                    OR icl_t.element_type NOT IN ({$icl_taxonomies}) OR icl_t.element_type IS NULL ) ";

		return $clauses;

	}

	private function get_where_lang() {
		$lang = $this->sitepress->get_current_language();
		if ( 'all' === $lang ) {
			return '';
		} else {
			$display_as_translated_snippet = $this->get_display_as_translated_snippet( $lang, $this->sitepress->get_default_language() );
			$language                     = $this->wpdb->prepare( '%s', $lang );

			return ' AND ( icl_t.language_code = ' . $language . ' OR ' . $display_as_translated_snippet . ' ) ';
		}
	}

	private function maybe_apply_count_adjustment( $clauses ) {
		if ( $this->should_apply_display_as_translated_adjustments() ) {
			return $this->display_as_translated_query->update_count(
				$clauses,
				$this->sitepress->get_default_language()
			);
		}

		return $clauses;
	}

	private function get_display_as_translated_snippet( $current_language, $fallback_language ) {
		if ( $this->should_apply_display_as_translated_adjustments() ) {
			return $this->display_as_translated_query->get_language_snippet(
				$current_language,
				$fallback_language,
				$this->get_display_as_translated_taxonomies()
			);
		}

		return '0';
	}

	private function should_apply_display_as_translated_adjustments() {
		return $this->get_display_as_translated_taxonomies() && ( ! is_admin() || WPML_Ajax::is_frontend_ajax_request() );
	}

	private function get_display_as_translated_taxonomies() {
		if ( $this->cache === null ) {
			$this->cache = $this->sitepress->get_display_as_translated_taxonomies();
		}

		return $this->cache;
	}
}
