<?php

use \WPML\FP\Relation;
use \WPML\FP\Fns;
use WPML\TaxonomyTermTranslation\Hooks as TermTranslationHooks;

class WPML_Term_Query_Filter {

	const SLUG_MEMO_DROPPED_ON = [ 'created_term', 'edited_term', 'delete_term', 'clean_term_cache', 'wpml_translation_update' ];

	private $slug_memo;

	private $term_translation;

	private $wpdb;

	private $taxonomy_state;

	private $current_language;

	private $default_language;

	private $lock;

	private $opt_out;

	private $update_term_slug_check;

	public function __construct(
		WPML_Term_Translation $term_translation,
		wpdb $wpdb,
		IWPML_Taxonomy_State $taxonomy_state,
		?WPML_Term_Query_Opt_Out $opt_out = null
	) {
		$this->term_translation = $term_translation;
		$this->wpdb             = $wpdb;
		$this->taxonomy_state   = $taxonomy_state;
		$this->opt_out          = $opt_out ? $opt_out : new WPML_Term_Query_Opt_Out();
	}

	public function set_lang( $current_language, $default_language ) {
		$this->current_language = $current_language;
		$this->default_language = $default_language;
	}

	public function note_update_term_slug_check( $parent, $term_id, $taxonomy ) {
		$this->update_term_slug_check = [ (string) $taxonomy, (int) $term_id ];

		return $parent;
	}

	public function get_terms_args_filter( $args, $taxonomies ) {
		$language = $this->take_update_term_slug_check_language( $args, $taxonomies );
		if ( ! $language || $language === $this->current_language ) {
			return $this->filter_args( $args, $taxonomies );
		}

		global $icl_adjust_id_url_filter_off;

		$current_language             = $this->current_language;
		$adjust_id_was_off            = $icl_adjust_id_url_filter_off;
		$this->current_language       = $language;
		$icl_adjust_id_url_filter_off = true;
		try {
			return $this->filter_args( $args, $taxonomies );
		} finally {
			$this->current_language       = $current_language;
			$icl_adjust_id_url_filter_off = $adjust_id_was_off;
		}
	}

	private function take_update_term_slug_check_language( $args, $taxonomies ) {
		if ( ! $this->update_term_slug_check || empty( $args['slug'] ) ) {
			return null;
		}

		list( $taxonomy, $term_id ) = $this->update_term_slug_check;
		if ( ! in_array( $taxonomy, (array) $taxonomies, true ) ) {
			return null;
		}

		$this->update_term_slug_check = null;

		$language = $this->term_translation->lang_code_by_termid( $term_id );

		return $language ? (string) $language : null;
	}

	private function filter_args( $args, $taxonomies ) {
		$keep_named_translations = false;
		if ( $this->opt_out->is_id_adjustment_opted_out() ) {
			$stand_down = $this->opt_out->is_stand_down_requested();

			if ( $stand_down && ! $this->opt_out->is_site_id_adjustment_opted_out() ) {
				$keep_named_translations = true;
			} else {
				return $stand_down
					? $args
					: $this->pin_slug_to_current_language( $args, $taxonomies );
			}
		}

		if ( $this->lock ) {
			return $args;
		}

		if ( class_exists( 'WPML\TaxonomyTermTranslation\Hooks' ) && TermTranslationHooks::shouldSkip( $args ) ) {
			return $args;
		}

		if ( 0 === count( array_filter( (array) $taxonomies, array( $this->taxonomy_state, 'is_translated_taxonomy' ) ) ) ) {
			return $args;
		}

		if ( $keep_named_translations ) {
			return $this->adjust_args_keeping_named_translations( $args, $taxonomies );
		}

		$slug_memo_key = $this->slug_memo_key( $args, $taxonomies );
		if ( null !== $slug_memo_key ) {
			$args = $this->slug_memo()->remember(
				$slug_memo_key,
				function () use ( $args, $taxonomies ) {
					return $this->adjust_args( $args, $taxonomies );
				}
			);
		} else {
			$args = $this->adjust_args( $args, $taxonomies );
		}

		return $args;
	}

	private function is_term_adjustment_disabled() {
		global $icl_adjust_id_url_filter_off;

		if ( ! has_filter( 'wpml_disable_term_adjust_id' ) ) {
			return (bool) $icl_adjust_id_url_filter_off;
		}

		try {
			return (bool) apply_filters( 'wpml_disable_term_adjust_id', $icl_adjust_id_url_filter_off, null );
		} catch ( Error $error ) {
			return true;
		}
	}

	private function slug_memo() {
		if ( ! $this->slug_memo ) {
			$this->slug_memo = new \WPML\Utilities\RequestMemo( self::SLUG_MEMO_DROPPED_ON );
		}

		return $this->slug_memo;
	}

	private function slug_memo_key( $args, $taxonomies ) {
		if ( empty( $args['slug'] ) ) {
			return null;
		}

		try {
			return md5( serialize( [ $this->current_language, $this->default_language, is_admin(), $this->are_terms_adjusted(), (array) $taxonomies, $args ] ) );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private function are_terms_adjusted() {
		global $sitepress;

		return $sitepress instanceof SitePress
			&& false !== has_filter( 'get_term', [ $sitepress, 'get_term_adjust_id' ] )
			&& ! $this->is_term_adjustment_disabled();
	}

	private function adjust_args( $args, $taxonomies ) {
		$this->lock = true;

		if ( isset( $args['cache_domain'] ) ) {
			$args['cache_domain'] .= '_' . $this->current_language . ( $this->are_terms_adjusted() ? '_adj' : '' );
		}

		$isOrderByEqualTo = Relation::propEq( 'orderby', Fns::__, $args );

		$params = array( 'include', 'exclude', 'exclude_tree' );
		foreach ( $params as $param ) {
			$adjusted_ids = $this->adjust_taxonomies_terms_ids( $args[ $param ], $isOrderByEqualTo( $param ) );

			if ( ! empty( $adjusted_ids ) ) {
				$args[ $param ] = $adjusted_ids;
			}
		}

		$params = array( 'child_of', 'parent' );
		foreach ( $params as $param ) {
			if ( ! isset( $args[ $param ] ) ) {
				continue;
			}

			$adjusted_ids = $this->adjust_taxonomies_terms_ids( $args[ $param ], $isOrderByEqualTo( $param ) );

			if ( ! empty( $adjusted_ids ) ) {
				$args[ $param ] = array_pop( $adjusted_ids );
			}
		}

		if ( ! empty( $args['slug'] ) ) {
			$args = $this->adjust_taxonomies_terms_slugs( $args, $taxonomies );
		}

		$this->lock = false;
		return $args;
	}

	private function adjust_args_keeping_named_translations( $args, $taxonomies ) {
		$taxonomies = (array) $taxonomies;

		$this->lock = true;

		$slugs = empty( $args['slug'] ) ? [] : array_values( array_filter( (array) $args['slug'], 'strlen' ) );
		$slug_targets = [];
		foreach ( $slugs as $slug ) {
			$term                  = $this->guess_term( (string) $slug, $taxonomies );
			$slug_targets[ $slug ] = $term ? $this->translation_target( (int) $term->term_id, $term->taxonomy ) : null;
		}

		$ids        = empty( $args['include'] ) ? [] : array_values( array_filter( array_map( 'intval', $this->explode_and_trim( $args['include'] ) ) ) );
		$id_targets = [];
		foreach ( $this->get_terms( $ids, false ) as $term ) {
			$id_targets[ (int) $term->term_id ] = $this->translation_target( (int) $term->term_id, $term->taxonomy );
		}

		$kept_slugs = $this->named_with_a_translation( $slug_targets );
		$kept_ids   = $this->named_with_a_translation( $id_targets );

		$this->lock = false;

		if ( ! $kept_slugs && ! $kept_ids ) {
			return $this->adjust_args( $args, $taxonomies );
		}

		$to_adjust = $args;
		if ( $kept_slugs ) {
			$to_adjust['slug'] = array_values( array_diff( $slugs, $kept_slugs ) );
		}
		if ( $kept_ids ) {
			$to_adjust['include'] = array_values( array_diff( $ids, $kept_ids ) );
		}

		$adjusted = $this->adjust_args( $to_adjust, $taxonomies );

		if ( $kept_slugs ) {
			$adjusted['slug'] = array_values( array_merge( array_filter( (array) $adjusted['slug'], 'strlen' ), $kept_slugs ) );
			if ( empty( $args['term_taxonomy_id'] ) ) {
				if ( array_key_exists( 'term_taxonomy_id', $args ) ) {
					$adjusted['term_taxonomy_id'] = $args['term_taxonomy_id'];
				} else {
					unset( $adjusted['term_taxonomy_id'] );
				}
			}
		}
		if ( $kept_ids ) {
			$adjusted['include'] = array_values( array_merge( array_filter( array_map( 'intval', $this->explode_and_trim( $adjusted['include'] ) ) ), $kept_ids ) );
		}

		return $adjusted;
	}

	private function translation_target( $term_id, $taxonomy ) {
		if ( ! $this->taxonomy_state->is_translated_taxonomy( $taxonomy ) ) {
			return null;
		}

		$target = $this->term_translation->term_id_in( $term_id, $this->current_language );

		return $target ? (int) $target : null;
	}

	private function named_with_a_translation( array $targets ) {
		$counts = array_count_values( array_map( 'strval', array_filter( $targets ) ) );

		$kept = [];
		foreach ( $targets as $named => $target ) {
			if ( $target && $counts[ (string) $target ] > 1 ) {
				$kept[] = $named;
			}
		}

		return $kept;
	}

	private function adjust_taxonomies_terms_ids( $terms_ids, $orderByTermId ) {
		$terms_ids = array_filter( array_unique( $this->explode_and_trim( $terms_ids ) ) );

		if ( empty( $terms_ids ) ) {
			return $terms_ids;
		}

		$terms          = $this->get_terms( $terms_ids, $orderByTermId );
		$translated_ids = array();

		$this->prefetch_translations( $terms );

		foreach ( $terms as $term ) {

			if ( $this->taxonomy_state->is_translated_taxonomy( $term->taxonomy ) ) {
				$translated_id = $this->term_translation->term_id_in( $term->term_id, $this->current_language );
				if ( ! $translated_id && ! is_admin() && $this->taxonomy_state->is_display_as_translated_taxonomy( $term->taxonomy ) ) {
					$translated_id = $this->term_translation->term_id_in( $term->term_id, $this->default_language );
				}
				$translated_ids[] = $translated_id;
			} else {
				$translated_ids[] = $term->term_id;
			}
		}

		return array_filter( $translated_ids );
	}

	private function prefetch_translations( $terms ) {
		$ttids = array();
		foreach ( $terms as $term ) {
			if ( ! empty( $term->term_taxonomy_id ) && $this->taxonomy_state->is_translated_taxonomy( $term->taxonomy ) ) {
				$ttids[] = (int) $term->term_taxonomy_id;
			}
		}

		if ( count( $ttids ) > 1 ) {
			$this->term_translation->prefetch_ids( $ttids );
		}
	}

	private function adjust_taxonomies_terms_slugs( $args, array $taxonomies ) {
		$terms_slugs = $args['slug'];
		if ( is_string( $terms_slugs ) ) {
			$terms_slugs = [ $terms_slugs ];
		}

		$duplicateSlugTranslations = [];
		$translated_slugs          = [];
		$translated_term           = null;
		foreach ( $terms_slugs as $terms_slug ) {
			$term = $this->guess_term( $terms_slug, $taxonomies );

			if ( $term ) {
				$translated_id   = $this->term_translation->term_id_in( $term->term_id, $this->current_language );
				$translated_term = get_term( $translated_id, $term->taxonomy );
				if ( $translated_term instanceof WP_Term ) {
					if ( $terms_slug === $translated_term->slug ) {
						$duplicateSlugTranslations[] = $translated_id;
					}
					$terms_slug = $translated_term->slug;
				}
			}
			$translated_slugs[] = $terms_slug;
		}

		if ( is_admin() && count( $duplicateSlugTranslations ) === 1 ) {
			$args['include'] = $duplicateSlugTranslations;
			$args['slug']    = '';
		} else {
			$args['slug'] = array_filter( $translated_slugs );

			if (
				! is_admin()
				&& 1 === count( $terms_slugs )
				&& $translated_term instanceof WP_Term
				&& empty( $args['term_taxonomy_id'] )
				&& count( $this->term_translation->term_ids_by_slug( $translated_term->slug, $translated_term->taxonomy ) ) > 1
			) {
				$args['term_taxonomy_id'] = [ (int) $translated_term->term_taxonomy_id ];
			}
		}

		return $args;
	}

	private function pin_slug_to_current_language( $args, $taxonomies ) {
		$taxonomies = (array) $taxonomies;
		$slugs      = empty( $args['slug'] ) ? [] : (array) $args['slug'];

		if (
			$this->lock
			|| 1 !== count( $slugs )
			|| 1 !== count( $taxonomies )
			|| ! empty( $args['term_taxonomy_id'] )
			|| ! $this->current_language
			|| 'all' === $this->current_language
			|| ( class_exists( 'WPML\TaxonomyTermTranslation\Hooks' ) && TermTranslationHooks::shouldSkip( $args ) )
		) {
			return $args;
		}

		$slug     = (string) reset( $slugs );
		$taxonomy = (string) reset( $taxonomies );
		if (
			! $this->taxonomy_state->is_translated_taxonomy( $taxonomy )
			|| count( $this->term_translation->term_ids_by_slug( $slug, $taxonomy ) ) < 2
		) {
			return $args;
		}

		$term = $this->term_in_current_language( $slug, $taxonomy );
		if ( $term ) {
			$args['term_taxonomy_id'] = [ (int) $term->term_taxonomy_id ];
		}

		return $args;
	}

	private function get_terms( $ids, $orderByTermId ) {
		if ( ! $ids ) {
			return array();
		}

		$wpdb = $this->wpdb;
		$ids  = array_map( 'intval', $ids );
		if ( $orderByTermId ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT taxonomy, term_id, term_taxonomy_id FROM {$wpdb->term_taxonomy}
					WHERE term_id IN (" . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')
					ORDER BY FIELD(term_id, ' . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')',
					array_merge( $ids, $ids )
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT taxonomy, term_id, term_taxonomy_id FROM {$wpdb->term_taxonomy}
				WHERE term_id IN (" . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')',
				$ids
			)
		);
	}

	private function guess_term( $slug, array $taxonomies ) {
		foreach ( $taxonomies as $taxonomy ) {
			$term = $this->term_in_current_language( $slug, $taxonomy );

			if ( ! $term ) {
				$term = get_term_by( 'slug', $slug, $taxonomy );
			}

			if ( $term ) {
				return $term;
			}
		}

		return null;
	}

	private function term_in_current_language( $slug, $taxonomy ) {
		foreach ( $this->term_translation->term_ids_by_slug( $slug, $taxonomy ) as $term_id ) {
			if ( $this->term_translation->lang_code_by_termid( $term_id ) === $this->current_language ) {
				$term = get_term( $term_id, $taxonomy );

				return $term instanceof WP_Term ? $term : null;
			}
		}

		return null;
	}

	private function explode_and_trim( $source ) {
		if ( ! is_array( $source ) ) {
			$source = array_map( 'trim', explode( ',', $source ) );
		}

		return $source;
	}

}
