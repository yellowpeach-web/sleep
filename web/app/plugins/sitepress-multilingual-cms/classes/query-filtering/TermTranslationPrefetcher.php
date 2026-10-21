<?php

namespace WPML\QueryFiltering;

class TermTranslationPrefetcher implements \IWPML_Action, \IWPML_Frontend_Action_Loader, \IWPML_Backend_Action_Loader {

	private $term_translations;

	private $sitepress;

	private $prefetched_objects = [];

	public function __construct( ?\WPML_Term_Translation $termTranslations = null, ?\SitePress $sitepress = null ) {
		$this->term_translations = $termTranslations;
		$this->sitepress         = $sitepress;
	}

	public function create() {
		return $this;
	}

	public function add_hooks(): void {
		add_filter( 'terms_pre_query', [ $this, 'prefetchTermQuery' ], 10, 2 );
		foreach ( [ 'set_object_terms', 'added_term_relationship', 'deleted_term_relationships', 'clean_term_cache', 'wpml_translation_update' ] as $action ) {
			add_action( $action, [ $this, 'forgetPrefetchedObjects' ] );
		}
	}

	public function forgetPrefetchedObjects() {
		$this->prefetched_objects = [];
	}

	public function prefetchTermQuery( $terms, $query ) {
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

		$ttids = $this->termTaxonomyIdsFor( $qv );
		if ( $ttids ) {
			$term_translations->prefetch_ids( $ttids );
		}

		return $terms;
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
					"SELECT DISTINCT term_taxonomy_id FROM {$wpdb->term_relationships}
					 WHERE object_id IN (" . implode( ',', $ids ) . ')'
				)
			);
		}

		foreach ( [ 'term_taxonomy_id', 'include' ] as $key ) {
			if ( ! empty( $qv[ $key ] ) ) {
				return array_filter( array_map( 'intval', (array) $qv[ $key ] ) );
			}
		}

		return [];
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
