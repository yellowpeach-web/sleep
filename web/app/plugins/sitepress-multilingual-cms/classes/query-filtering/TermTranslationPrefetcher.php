<?php

namespace WPML\QueryFiltering;

class TermTranslationPrefetcher implements \IWPML_Action, \IWPML_Frontend_Action_Loader, \IWPML_Backend_Action_Loader {

	private $term_translations;

	private $sitepress;

	private $prefetched_objects = [];

	private $pending_term_queries = [];

	private $term_clauses;

	private const MAX_PENDING_TERM_QUERIES = 20;

	public function __construct( ?\WPML_Term_Translation $termTranslations = null, ?\SitePress $sitepress = null ) {
		$this->term_translations = $termTranslations;
		$this->sitepress         = $sitepress;
	}

	public function create() {
		return $this;
	}

	public function add_hooks(): void {
		add_filter( 'terms_clauses', [ $this, 'noteTermClauses' ], PHP_INT_MAX );
		add_filter( 'terms_pre_query', [ $this, 'prefetchTermQuery' ], 10, 2 );
		add_filter( 'get_term', [ $this, 'warmPendingTermQuery' ], 0 );
		add_filter( 'get_terms', [ $this, 'forgetPendingTermQuery' ], PHP_INT_MAX, 4 );
		add_filter( 'pre_wp_nav_menu', [ $this, 'prefetchMenuTerms' ], PHP_INT_MAX, 2 );
		foreach ( [ 'set_object_terms', 'added_term_relationship', 'deleted_term_relationships', 'clean_term_cache', 'wpml_translation_update' ] as $action ) {
			add_action( $action, [ $this, 'forgetPrefetchedObjects' ] );
		}
	}

	public function forgetPrefetchedObjects() {
		$this->prefetched_objects   = [];
		$this->pending_term_queries = [];
	}

	public function prefetchTermQuery( $terms, $query ) {
		$clauses            = $this->term_clauses;
		$this->term_clauses = null;
		if ( null !== $terms ) {
			return $terms;
		}
		$qv = $query->query_vars;
		if ( 'count' === ( $qv['fields'] ?? '' ) ) {
			return $terms;
		}
		if ( ! $this->warmsHere() ) {
			return $terms;
		}
		$term_translations = $this->termTranslations();
		if ( ! $term_translations ) {
			return $terms;
		}

		if ( $this->namesNoIds( $qv ) ) {
			$this->rememberPendingTermQuery( $query, $clauses );

			return $terms;
		}

		$ttids = $this->termTaxonomyIdsFor( $qv );
		if ( $ttids ) {
			$term_translations->prefetch_ids( $ttids );
		}

		return $terms;
	}

	private function namesNoIds( $qv ) {
		return empty( $qv['object_ids'] ) && empty( $qv['term_taxonomy_id'] ) && empty( $qv['include'] );
	}

	private function rememberPendingTermQuery( $query, $clauses ) {
		$qv = $query->query_vars;
		if ( 1 === (int) ( $qv['number'] ?? 0 ) ) {
			return;
		}
		$sql = $this->termTaxonomyIdsSql( $clauses, (string) ( $query->request ?? '' ) );
		if ( '' === $sql ) {
			return;
		}
		$sitepress  = $this->sitepress();
		$taxonomies = array_values(
			array_filter(
				(array) ( $qv['taxonomy'] ?? [] ),
				function ( $taxonomy ) use ( $sitepress ) {
					return is_string( $taxonomy ) && $sitepress->is_translated_taxonomy( $taxonomy );
				}
			)
		);
		if ( ! $taxonomies ) {
			return;
		}

		$this->pending_term_queries = array_slice(
			array_values(
				array_filter(
					$this->pending_term_queries,
					function ( $pending ) {
						return ! $pending['done'];
					}
				)
			),
			1 - self::MAX_PENDING_TERM_QUERIES
		);

		$this->pending_term_queries[] = [
			'query'      => $query,
			'taxonomies' => $taxonomies,
			'sql'        => $sql,
			'done'       => false,
		];
	}

	public function noteTermClauses( $clauses ) {
		$this->term_clauses = is_array( $clauses ) ? $clauses : null;

		return $clauses;
	}

	public function warmPendingTermQuery( $term ) {
		if ( ! $this->pending_term_queries ) {
			return $term;
		}
		$key     = count( $this->pending_term_queries ) - 1;
		$pending = $this->pending_term_queries[ $key ];
		if ( $pending['done'] || ! is_object( $term ) || ! isset( $term->term_taxonomy_id, $term->taxonomy )
			|| ! in_array( $term->taxonomy, $pending['taxonomies'], true )
		) {
			return $term;
		}
		$term_translations = $this->termTranslations();
		if ( ! $term_translations || null !== $term_translations->get_cached_trid( (int) $term->term_taxonomy_id ) ) {
			return $term;
		}

		$this->pending_term_queries[ $key ]['done'] = true;
		$ttids                                      = $this->termTaxonomyIdsListedBy( $pending['sql'] );
		if ( $ttids ) {
			$term_translations->prefetch_ids( $ttids );
		}

		return $term;
	}

	public function forgetPendingTermQuery( $terms, $taxonomies = null, $args = null, $query = null ) {
		for ( $i = count( $this->pending_term_queries ) - 1; $i >= 0; $i-- ) {
			if ( $this->pending_term_queries[ $i ]['query'] === $query ) {
				array_splice( $this->pending_term_queries, $i );
				break;
			}
		}

		return $terms;
	}

	private function termTaxonomyIdsSql( $clauses, $request ) {
		global $wpdb;

		if ( ! is_array( $clauses ) || '' === $request ) {
			return '';
		}
		$from    = "FROM {$wpdb->terms} AS t " . ( $clauses['join'] ?? '' );
		$where   = (string) ( $clauses['where'] ?? '' );
		$where   = '' !== $where ? "WHERE {$where}" : '';
		$orderby = (string) ( $clauses['orderby'] ?? '' );
		$orderby = '' !== $orderby ? $orderby . ' ' . ( $clauses['order'] ?? '' ) : '';
		$limits  = (string) ( $clauses['limits'] ?? '' );
		if ( false === strpos( $request, $from ) || ( '' !== $where && false === strpos( $request, $where ) ) ) {
			return '';
		}

		return "SELECT tt.term_taxonomy_id {$from} {$where}" . ( '' !== $limits ? " {$orderby} {$limits}" : '' );
	}

	private function termTaxonomyIdsListedBy( $sql ) {
		global $wpdb;

		$ids = $wpdb->get_col( $sql );
		if ( ! empty( $wpdb->last_error ) || ! is_array( $ids ) ) {
			return [];
		}

		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	public function prefetchMenuTerms( $output, $args ) {
		if ( null !== $output || ! $this->warmsHere() ) {
			return $output;
		}
		$term_translations = $this->termTranslations();
		$menu              = $term_translations ? $this->menuOf( $args ) : null;
		if ( ! $menu || empty( $menu->count ) ) {
			return $output;
		}

		global $wpdb;
		$ttids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT tt.term_taxonomy_id
				FROM {$wpdb->term_relationships} tr
				JOIN {$wpdb->postmeta} item_type ON item_type.post_id = tr.object_id AND item_type.meta_key = '_menu_item_type' AND item_type.meta_value = 'taxonomy'
				JOIN {$wpdb->postmeta} item_object ON item_object.post_id = tr.object_id AND item_object.meta_key = '_menu_item_object'
				JOIN {$wpdb->postmeta} item_object_id ON item_object_id.post_id = tr.object_id AND item_object_id.meta_key = '_menu_item_object_id'
				JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = CAST( item_object_id.meta_value AS UNSIGNED ) AND tt.taxonomy = item_object.meta_value
				WHERE tr.term_taxonomy_id = %d",
				$menu->term_taxonomy_id
			)
		);
		if ( $ttids ) {
			$term_translations->prefetch_ids( array_map( 'intval', $ttids ) );
		}

		return $output;
	}

	private function menuOf( $args ) {
		if ( ! is_object( $args ) ) {
			return null;
		}
		$menu = ! empty( $args->menu ) ? wp_get_nav_menu_object( $args->menu ) : false;
		if ( ! $menu && ! empty( $args->theme_location ) ) {
			$locations = get_nav_menu_locations();
			if ( isset( $locations[ $args->theme_location ] ) ) {
				$menu = wp_get_nav_menu_object( $locations[ $args->theme_location ] );
			}
		}

		return $menu instanceof \WP_Term ? $menu : null;
	}

	private function warmsHere() {
		$sitepress = $this->sitepress();
		if ( ! $sitepress || ! $sitepress->get_setting( 'auto_adjust_ids' ) ) {
			return false;
		}

		return ! ( ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) || wpml_is_rest_request() );
	}

	private function termTaxonomyIdsFor( $qv ) {
		global $wpdb;

		if ( ! empty( $qv['object_ids'] ) ) {
			$ids = array_values(
				array_filter(
					array_map( 'intval', (array) $qv['object_ids'] ),
					function ( $id ) {
						return $id && ! isset( $this->prefetched_objects[ $id ] );
					}
				)
			);
			if ( ! $ids ) {
				return [];
			}
			foreach ( $ids as $id ) {
				$this->prefetched_objects[ $id ] = true;
			}
			return array_map(
				'intval',
				$wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT term_taxonomy_id FROM {$wpdb->term_relationships}
						 WHERE object_id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
						$ids
					)
				)
			);
		}

		if ( ! empty( $qv['term_taxonomy_id'] ) ) {
			return array_values( array_filter( wp_parse_id_list( $qv['term_taxonomy_id'] ) ) );
		}

		if ( ! empty( $qv['include'] ) ) {
			return $this->termTaxonomyIdsOfTermIds( array_values( array_filter( wp_parse_id_list( $qv['include'] ) ) ), $qv['taxonomy'] ?? [] );
		}

		return [];
	}

	private function termTaxonomyIdsOfTermIds( array $term_ids, $taxonomies ) {
		global $wpdb;

		$taxonomies = array_values( array_filter( (array) $taxonomies, 'is_string' ) );
		$ttids      = [];
		$unknown    = [];
		foreach ( $term_ids as $term_id ) {
			$term = wp_cache_get( $term_id, 'terms' );
			if ( is_object( $term ) && isset( $term->term_taxonomy_id, $term->taxonomy )
				&& ( ! $taxonomies || in_array( $term->taxonomy, $taxonomies, true ) )
			) {
				$ttids[] = (int) $term->term_taxonomy_id;
			} else {
				$unknown[] = (int) $term_id;
			}
		}

		if ( $unknown && $taxonomies ) {
			$found = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id IN (" . implode( ',', array_fill( 0, count( $unknown ), '%d' ) ) . ') AND taxonomy IN (' . implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) ) . ')',
					array_merge( $unknown, $taxonomies )
				)
			);
			$ttids = array_merge( $ttids, array_map( 'intval', $found ) );
		} elseif ( $unknown ) {
			$found = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id IN (" . implode( ',', array_fill( 0, count( $unknown ), '%d' ) ) . ')',
					$unknown
				)
			);
			$ttids = array_merge( $ttids, array_map( 'intval', $found ) );
		}

		return $ttids;
	}

	private function termTranslations() {
		if ( $this->term_translations ) {
			return $this->term_translations;
		}
		global $wpml_term_translations;

		return $wpml_term_translations;
	}

	private function sitepress() {
		if ( $this->sitepress ) {
			return $this->sitepress;
		}
		global $sitepress;

		return $sitepress;
	}
}
