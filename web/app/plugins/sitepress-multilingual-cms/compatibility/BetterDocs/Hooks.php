<?php

namespace WPML\Compatibility\BetterDocs;

class Hooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const HELPER = '\WPDeveloper\BetterDocs\Utils\Helper';

	public function add_hooks() {
		add_filter( 'betterdocs_terms_query_args', [ $this, 'orderByLanguageKey' ], 20 );
		add_filter( 'betterdocs_articles_args', [ $this, 'orderDocsOfTranslatedCategory' ], 20, 3 );
		add_filter( 'betterdocs_adjacent_docs_order', [ $this, 'orderAdjacentDocs' ], 20, 2 );
		add_action( 'pre_get_posts', [ $this, 'orderArchiveQuery' ], 20 );
		add_filter( 'rest_request_after_callbacks', [ $this, 'orderOrganizerDocs' ], 20, 3 );
	}

	public function orderOrganizerDocs( $response, $handler = [], $request = null ) {
		if ( ! $request instanceof \WP_REST_Request || '/betterdocs/v1/doc-categories' !== rtrim( (string) $request->get_route(), '/' ) ) {
			return $response;
		}

		$isObject = $response instanceof \WP_REST_Response;
		$body     = $isObject ? $response->get_data() : $response;
		if ( ! is_array( $body ) || ! isset( $body['data'] ) || ! is_array( $body['data'] ) ) {
			return $response;
		}

		$defaultLang = apply_filters( 'wpml_default_language', null );
		$changed     = false;
		foreach ( $body['data'] as $termId => $rows ) {
			if ( ! is_int( $termId ) || $termId <= 0 || ! is_array( $rows ) || ! $rows ) {
				continue;
			}

			$termLang = $this->termLanguage( $termId );
			if ( ! $termLang || 'all' === $termLang || $defaultLang === $termLang ) {
				continue;
			}

			$ordered = $this->orderedDocIds( $termId, $termLang, [] );
			if ( $ordered ) {
				$body['data'][ $termId ] = $this->sortRows( $rows, $ordered );
				$changed                 = true;
			}
		}

		if ( ! $changed ) {
			return $response;
		}
		if ( $isObject ) {
			$response->set_data( $body );

			return $response;
		}

		return $body;
	}

	private function termLanguage( $termId ) {
		$term = get_term( $termId, 'doc_category' );
		if ( ! $term instanceof \WP_Term ) {
			return null;
		}

		return apply_filters(
			'wpml_element_language_code',
			null,
			[
				'element_id'   => (int) $term->term_taxonomy_id,
				'element_type' => 'doc_category',
			]
		);
	}

	private function sortRows( array $rows, array $ordered ) {
		$position = array_flip( $ordered );
		$listed   = [];
		$others   = [];
		foreach ( $rows as $row ) {
			$id = is_array( $row ) && isset( $row['id'] ) ? (int) $row['id'] : 0;
			if ( isset( $position[ $id ] ) ) {
				$listed[ $position[ $id ] ] = $row;
			} else {
				$others[] = $row;
			}
		}
		ksort( $listed );

		return array_merge( array_values( $listed ), $others );
	}

	public function orderDocsOfTranslatedCategory( $args, $termId = null, $originArgs = [] ) {
		$originArgs = is_array( $originArgs ) ? $originArgs : [];
		$askedOrder = isset( $originArgs['orderby'] ) ? $originArgs['orderby'] : 'betterdocs_order';
		if (
			! is_array( $args )
			|| ! is_numeric( $termId )
			|| (int) $termId <= 0
			|| ! isset( $args['orderby'], $args['post__in'] )
			|| 'post__in' !== $args['orderby']
			|| ! is_array( $args['post__in'] )
			|| 'betterdocs_order' !== $askedOrder
			|| ! empty( $originArgs['post__in'] )
		) {
			return $args;
		}

		$lang = $this->secondaryLanguage();
		if ( ! $lang ) {
			return $args;
		}

		if ( empty( $args['post__in'] ) && ! $this->originalList( (int) $termId, $lang ) ) {
			return $args;
		}

		$ordered = $this->orderedDocIds( (int) $termId, $lang, $args['post__in'] );
		if ( $ordered ) {
			$args['post__in'] = $ordered;
		}

		return $args;
	}

	public function orderAdjacentDocs( $docsOrder, $terms = [] ) {
		if ( empty( $docsOrder ) || ! is_array( $docsOrder ) ) {
			return $docsOrder;
		}

		$lang = $this->secondaryLanguage();
		if ( ! $lang ) {
			return $docsOrder;
		}

		$termId = $this->firstDocCategory( $terms );
		if ( ! $termId || ! $this->walksTheCategoryList( $termId, $lang ) ) {
			return $docsOrder;
		}

		return $this->orderedDocIds( $termId, $lang, $docsOrder );
	}

	public function orderArchiveQuery( $query ) {
		if ( ! $query instanceof \WP_Query || ! $query->is_main_query() || ! $query->is_tax( 'doc_category' ) ) {
			return;
		}

		$orderBy = $query->get( 'orderby' );
		$postIn  = $query->get( 'post__in' );
		$listed  = 'post__in' === $orderBy && $postIn && is_array( $postIn );
		$unlisted = ! $orderBy && ! $postIn;
		if ( ! $listed && ! $unlisted ) {
			return;
		}

		$lang = $this->secondaryLanguage();
		if ( ! $lang ) {
			return;
		}

		$term = $query->get_queried_object();
		if ( ! $term instanceof \WP_Term || 'doc_category' !== $term->taxonomy || (int) $term->term_id <= 0 ) {
			return;
		}
		$termId = (int) $term->term_id;

		if ( $listed ) {
			$query->set( 'post__in', $this->orderedDocIds( $termId, $lang, $postIn ) );

			return;
		}

		if ( ! $this->ordersByBetterDocsOrder() || ! $this->originalList( $termId, $lang ) ) {
			return;
		}

		$ordered = $this->orderedDocIds( $termId, $lang, [] );
		if ( $ordered ) {
			$query->set( 'orderby', 'post__in' );
			$query->set( 'post__in', $ordered );
		}
	}

	private function orderedDocIds( $termId, $lang, array $currentList ) {
		$published = $this->publishedDocIds( $termId, $lang );
		$own       = $this->ownList( $termId, $lang );
		$original  = $this->originalList( $termId, $lang );
		$base      = $own && array_intersect( $own, $published ) ? $this->mergeIntoOwnList( $own, $original ) : $original;
		$missing   = array_values( array_diff( $published, $base ) );
		$ordered   = array_values( array_unique( array_merge( $missing, $base ) ) );

		return $ordered ? $ordered : $currentList;
	}

	private function mergeIntoOwnList( array $own, array $original ) {
		$merged = array_values( array_unique( $own ) );

		foreach ( $original as $index => $id ) {
			if ( in_array( $id, $merged, true ) ) {
				continue;
			}

			$at = 0;
			for ( $before = $index - 1; $before >= 0; $before-- ) {
				$position = array_search( $original[ $before ], $merged, true );
				if ( false !== $position ) {
					$at = $position + 1;
					break;
				}
			}

			array_splice( $merged, $at, 0, [ $id ] );
		}

		return $merged;
	}

	private function ownList( $termId, $lang ) {
		return $this->idList( get_term_meta( $termId, $this->languageKey( $termId, $lang ), true ) );
	}

	private function originalList( $termId, $lang ) {
		$defaultLang    = apply_filters( 'wpml_default_language', null );
		$originalTermId = apply_filters( 'wpml_object_id', $termId, 'doc_category', false, $defaultLang );
		if ( ! is_numeric( $originalTermId ) || (int) $originalTermId <= 0 || (int) $originalTermId === $termId ) {
			return [];
		}
		$originalTermId = (int) $originalTermId;

		$mapped = [];
		foreach ( $this->idList( get_term_meta( $originalTermId, $this->languageKey( $originalTermId, $defaultLang ), true ) ) as $id ) {
			$translated = apply_filters( 'wpml_object_id', $id, 'docs', false, $lang );
			if ( is_numeric( $translated ) && (int) $translated > 0 ) {
				$mapped[] = (int) $translated;
			}
		}

		return array_values( array_unique( $mapped ) );
	}

	private function languageKey( $termId, $lang ) {
		if ( ! $lang ) {
			return '_docs_order';
		}

		$helper = self::HELPER;
		if ( class_exists( $helper ) && method_exists( $helper, 'get_meta_key_with_fallback' ) ) {
			return $helper::get_meta_key_with_fallback( '_docs_order', $termId, $lang );
		}

		$langKey = '_docs_order_' . $lang;

		return get_term_meta( $termId, $langKey, true ) ? $langKey : '_docs_order';
	}

	private function publishedDocIds( $termId, $lang ) {
		do_action( 'wpml_switch_language', $lang );
		try {
			$ids = $this->queryPublishedDocIds( $termId );
		} finally {
			do_action( 'wpml_switch_language', null );
		}

		return is_array( $ids ) ? $this->idList( $ids ) : [];
	}

	private function queryPublishedDocIds( $termId ) {
		return get_posts(
			[
				'post_type'        => 'docs',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => false,
				'no_found_rows'    => true,
				'tax_query'        => [
					[
						'taxonomy'         => 'doc_category',
						'field'            => 'term_id',
						'terms'            => $termId,
						'include_children' => false,
					],
				],
			]
		);
	}

	private function walksTheCategoryList( $termId, $lang ) {
		return $this->ordersByBetterDocsOrder() && ( $this->ownList( $termId, $lang ) || $this->originalList( $termId, $lang ) );
	}

	private function ordersByBetterDocsOrder() {
		$settings = get_option( 'betterdocs_settings', [] );
		$orderBy  = is_array( $settings ) && ! empty( $settings['alphabetically_order_post'] ) ? $settings['alphabetically_order_post'] : 'betterdocs_order';

		return 'betterdocs_order' === $orderBy;
	}

	private function firstDocCategory( $terms ) {
		if ( ! is_array( $terms ) ) {
			return 0;
		}

		foreach ( $terms as $term ) {
			if ( is_object( $term ) && isset( $term->term_id, $term->taxonomy ) && 'doc_category' === $term->taxonomy ) {
				return (int) $term->term_id;
			}
			if ( is_numeric( $term ) && (int) $term > 0 ) {
				return (int) $term;
			}
		}

		return 0;
	}

	private function idList( $value ) {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}
		if ( ! is_array( $value ) ) {
			return [];
		}

		return array_values(
			array_filter(
				array_map( 'intval', $value ),
				function ( $id ) {
					return $id > 0;
				}
			)
		);
	}

	private function secondaryLanguage() {
		$lang = apply_filters( 'wpml_current_language', null );
		if ( ! $lang || 'all' === $lang || apply_filters( 'wpml_default_language', null ) === $lang ) {
			return null;
		}

		return $lang;
	}

	public function orderByLanguageKey( $args ) {
		if (
			! is_array( $args )
			|| ( isset( $args['taxonomy'] ) ? $args['taxonomy'] : '' ) !== 'doc_category'
			|| ( isset( $args['meta_key'] ) ? $args['meta_key'] : '' ) !== 'doc_category_order'
		) {
			return $args;
		}

		$lang = apply_filters( 'wpml_current_language', null );
		if ( ! $lang || 'all' === $lang ) {
			return $args;
		}

		$helper = '\WPDeveloper\BetterDocs\Utils\Helper';
		if ( ! class_exists( $helper ) || ! method_exists( $helper, 'get_meta_key_with_fallback' ) ) {
			return $args;
		}

		$key  = $helper::get_meta_key_with_fallback( 'doc_category_order', null, $lang );
		$pair = [
			'relation' => 'OR',
			'bd_order' => [
				'key'     => $key,
				'type'    => 'NUMERIC',
				'compare' => 'EXISTS',
			],
			'bd_none'  => [
				'key'     => $key,
				'compare' => 'NOT EXISTS',
			],
		];

		unset( $args['meta_key'] );
		$args['meta_query'] = empty( $args['meta_query'] ) ? $pair : [ 'relation' => 'AND', $args['meta_query'], $pair ];
		$args['orderby']    = 'bd_order';

		return $args;
	}
}
