<?php

use WPML\Collect\Support\Collection;

class WPML_Display_As_Translated_Tax_Query implements IWPML_Action {

	const TERM_REGEX = '/term_taxonomy_id\s+(IN|in)\s*\(([^\)]+)\)/';

	private $sitepress;

	private $term_translation;

	public function __construct( SitePress $sitepress, WPML_Term_Translation $term_translation ) {
		$this->sitepress        = $sitepress;
		$this->term_translation = $term_translation;
	}

	public function add_hooks() {
		add_filter( 'posts_where', array( $this, 'posts_where_filter' ), 10, 2 );
	}

	public function posts_where_filter( $where, WP_Query $q ) {
		if ( $this->is_not_the_default_language() && $this->is_taxonomy_archive( $q ) ) {
			$post_types = $this->get_linked_post_types( $q );
			if ( $this->is_display_as_translated_mode( $post_types ) ) {
				$terms          = $this->find_terms( $where );
				$fallback_terms = $this->get_fallback_terms( $terms );
				$where          = $this->add_fallback_terms_to_where_clause( $where, $fallback_terms, $q );
			}
		}

		return $where;
	}

	private function is_not_the_default_language() {
		return $this->sitepress->get_default_language() !== $this->sitepress->get_current_language();
	}

	private function is_taxonomy_archive( WP_Query $q ) {
		$originalIsTaxonomyArchive = $q->is_archive() && ( $q->is_category() || $q->is_tax() || $q->is_tag() );

		return (bool) apply_filters( 'wpml_display_as_translated_tax_query_is_archive', $originalIsTaxonomyArchive, $q );
	}

	private function get_linked_post_types( WP_Query $q ) {
		$post_types = array();
		foreach ( $this->get_taxonomies( $this->get_tax_queries( $q ) ) as $taxonomy ) {
			$post_types = array_unique( array_merge( $post_types, WPML_WP_Taxonomy::get_linked_post_types( $taxonomy ) ) );
		}

		return $post_types;
	}

	private function get_tax_queries( WP_Query $q ) {
		return isset( $q->tax_query->queries ) ? $q->tax_query->queries : array();
	}

	private function get_taxonomies( array $queries, $with_children_only = false ) {
		$taxonomies = array();
		foreach ( $queries as $query ) {
			if ( isset( $query['taxonomy'] ) ) {
				if ( ! $with_children_only || ! empty( $query['include_children'] ) ) {
					$taxonomies[] = $query['taxonomy'];
				}
			} elseif ( is_array( $query ) ) {
				$taxonomies = array_merge( $taxonomies, $this->get_taxonomies( $query, $with_children_only ) );
			}
		}

		return array_unique( $taxonomies );
	}

	private function is_display_as_translated_mode( $post_types ) {
		foreach ( $post_types as $post_type ) {
			if ( $this->sitepress->is_display_as_translated_post_type( $post_type ) ) {
				return true;
			}
		}

		return false;
	}

	private function find_terms( $where ) {
		$terms = array();
		if ( preg_match_all( self::TERM_REGEX, $where, $matches ) ) {
			foreach ( $matches[2] as $terms_string ) {
				$terms_parts = explode( ',', $terms_string );
				$terms       = array_unique( array_merge( $terms, $terms_parts ) );
			}
		}

		return $terms;
	}

	private function get_fallback_terms( $terms ) {
		$default_language = $this->sitepress->get_default_language();
		$fallback_terms   = array();
		foreach ( $terms as $term ) {
			$translations = $this->term_translation->get_element_translations( (int) $term );
			if ( isset( $translations[ $default_language ] ) && ! in_array( $translations[ $default_language ], $fallback_terms ) ) {
				$fallback_terms[ $term ] = $translations[ $default_language ];
			}
		}

		return $fallback_terms;
	}

	private function add_fallback_terms_to_where_clause( $where, $fallback_terms, WP_Query $q ) {
		if ( preg_match_all( self::TERM_REGEX, $where, $matches ) ) {
			foreach ( $matches[2] as $index => $terms_string ) {
				$new_terms_string = $this->add_fallback_terms( $terms_string, $fallback_terms, $q );
				$original_block   = $matches[0][ $index ];
				$new_block        = str_replace( '(' . $terms_string . ')', '(' . $new_terms_string . ')', $original_block );
				$where            = str_replace( $original_block, $new_block, $where );
			}
		}

		return $where;
	}

	private function add_fallback_terms( $terms_string, $fallback_terms, WP_Query $q ) {
		$mergeFallbackTerms = function ( $term ) use ( $fallback_terms ) {
			return isset( $fallback_terms[ $term ] ) ? [ $term, $fallback_terms[ $term ] ] : $term;
		};

		$queriedObject = $q->get_queried_object();
		if ( isset( $queriedObject->taxonomy ) ) {
			$taxonomy      = $queriedObject->taxonomy;
			$mergeChildren = $this->include_term_children( $q )
				? function ( $term ) use ( $taxonomy ) {
					return [ $term, get_term_children( $term, $taxonomy ) ];
				}
				: \WPML\FP\Fns::identity();
		} else {
			$taxonomies      = $this->get_taxonomies( $this->get_tax_queries( $q ), true );
			$fallbackTermIds = array_flip( $fallback_terms );
			$mergeChildren   = function ( $term ) use ( $taxonomies, $fallbackTermIds ) {
				if ( ! isset( $fallbackTermIds[ $term ] ) ) {
					return $term;
				}

				$terms = [ $term ];
				foreach ( $taxonomies as $taxonomy ) {
					$children = get_term_children( (int) $this->term_translation->adjust_term_id_for_ttid( $term ), $taxonomy );
					foreach ( is_array( $children ) ? $children : [] as $child ) {
						$terms[] = $this->term_translation->adjust_ttid_for_term_id( $child );
					}
				}

				return $terms;
			};
		}

		return wpml_collect( explode( ',', $terms_string ) )
			->map( $mergeFallbackTerms )
			->flatten()
			->map( $mergeChildren )
			->flatten()
			->unique()
			->implode( ',' );
	}

	private function include_term_children( WP_Query $q ) {
		return (bool) \WPML\FP\Obj::path( [ 'tax_query', 'queries', 0, 'include_children' ], $q );
	}
}
