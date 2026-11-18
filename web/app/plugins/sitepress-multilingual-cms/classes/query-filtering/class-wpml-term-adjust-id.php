<?php

use WPML\TaxonomyTermTranslation\Hooks as TermTranslationHooks;

class WPML_Term_Adjust_Id {

	const WP_CLI_COMMANDS_NAMING_A_TERM = [
		'Term_Command',
		'Term_Meta_Command',
		'Menu_Command',
		'Menu_Item_Command',
	];

	private $debug_backtrace;

	private $term_translation;

	private $post_translation;

	private $sitepress;

	private $opt_out;

	private $translated_ttids = [];

	private $get_the_terms_to_adjust = [];

	private $relationships_to_store = [];

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
					'wp_delete_nav_menu',
					'wp_update_nav_menu_item',
				],
				false
			)
			|| $this->debug_backtrace->are_classes_in_call_stack( self::WP_CLI_COMMANDS_NAMING_A_TERM, false )
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

	public function mark_read_for_the_term_cache( $args, $object_ids, $taxonomies ) {
		$caller = $this->object_terms_caller();
		if ( 'is_object_in_term' === $caller ) {
			$this->store_after_the_request( $object_ids, $taxonomies );

			return $args;
		}
		if ( 'update_object_term_cache' !== $caller && 'get_the_terms' !== $caller ) {
			return $args;
		}

		$args                                           = (array) $args;
		$args[ TermTranslationHooks::KEY_SKIP_FILTERS ] = true;
		$args['cache_results']                          = false;

		if ( 'get_the_terms' === $caller ) {
			foreach ( (array) $object_ids as $object_id ) {
				foreach ( (array) $taxonomies as $taxonomy ) {
					$key = (int) $object_id . '|' . $taxonomy;

					$this->get_the_terms_to_adjust[ $key ] = ( isset( $this->get_the_terms_to_adjust[ $key ] ) ? $this->get_the_terms_to_adjust[ $key ] : 0 ) + 1;
				}
			}
		}

		return $args;
	}

	private function store_after_the_request( $object_ids, $taxonomies ) {
		foreach ( (array) $taxonomies as $taxonomy ) {
			foreach ( (array) $object_ids as $object_id ) {
				if ( (int) $object_id ) {
					$this->relationships_to_store[ (string) $taxonomy ][ (int) $object_id ] = true;
				}
			}
		}

		if ( false === has_action( 'shutdown', [ $this, 'store_the_read_terms' ] ) ) {
			add_action( 'shutdown', [ $this, 'store_the_read_terms' ], 0 );
		}
	}

	public function store_the_read_terms() {
		$to_store                     = $this->relationships_to_store;
		$this->relationships_to_store = [];

		foreach ( $to_store as $taxonomy => $objects ) {
			$object_ids = array_keys( $objects );
			$terms      = wp_get_object_terms(
				$object_ids,
				$taxonomy,
				[
					'fields'                               => 'all_with_object_id',
					TermTranslationHooks::KEY_SKIP_FILTERS => true,
					'cache_results'                        => false,
					'update_term_meta_cache'               => false,
				]
			);
			if ( is_wp_error( $terms ) ) {
				continue;
			}

			$stored = array_fill_keys( $object_ids, [] );
			foreach ( (array) $terms as $term ) {
				if ( isset( $term->object_id, $stored[ (int) $term->object_id ] ) ) {
					$stored[ (int) $term->object_id ][] = (int) $term->term_id;
				}
			}

			foreach ( $stored as $object_id => $term_ids ) {
				if ( false !== wp_cache_get( $object_id, "{$taxonomy}_relationships" ) ) {
					wp_cache_set( $object_id, $term_ids, "{$taxonomy}_relationships" );
				}
			}
		}
	}

	public function adjust_the_terms( $terms, $post_id, $taxonomy ) {
		$key = (int) $post_id . '|' . $taxonomy;
		if ( empty( $this->get_the_terms_to_adjust[ $key ] ) ) {
			return $terms;
		}

		$this->get_the_terms_to_adjust[ $key ]--;
		if ( ! $this->get_the_terms_to_adjust[ $key ] ) {
			unset( $this->get_the_terms_to_adjust[ $key ] );
		}

		if ( ! is_array( $terms ) ) {
			return $terms;
		}

		foreach ( $terms as $index => $term ) {
			if ( $term instanceof WP_Term ) {
				$adjusted = $this->sitepress->get_term_adjust_id( $term );
				if ( $adjusted instanceof WP_Term ) {
					$terms[ $index ] = $adjusted;
				}
			}
		}

		return $terms;
	}

	private function object_terms_caller() {
		$in_read = false;
		foreach ( (array) $this->debug_backtrace->get_backtrace() as $frame ) {
			$function = isset( $frame['class'] ) ? '' : (string) $frame['function'];
			if ( 'wp_get_object_terms' === $function ) {
				$in_read = true;
			} elseif ( $in_read ) {
				return $function;
			}
		}

		return '';
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
				$query_vars = $frame['object']->query_vars;

				return TermTranslationHooks::shouldSkip( $query_vars ) || ! $this->is_language_filtered( $query_vars );
			}
		}

		return false;
	}

	private function is_language_filtered( $query_vars ) {
		if ( 'all' === $this->sitepress->get_current_language() ) {
			return false;
		}

		$taxonomies = isset( $query_vars['taxonomy'] ) ? (array) $query_vars['taxonomy'] : [];
		foreach ( $taxonomies as $taxonomy ) {
			if ( $this->sitepress->is_translated_taxonomy( $taxonomy ) ) {
				return true;
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
