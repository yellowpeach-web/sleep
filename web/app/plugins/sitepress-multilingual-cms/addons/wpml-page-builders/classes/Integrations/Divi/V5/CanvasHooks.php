<?php

namespace WPML\Compatibility\Divi\V5;

use WPML\FP\Lst;
use SitePress;

class CanvasHooks implements \IWPML_DIC_Action, \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const META_KEY  = '_divi_canvas_parent_post_id';
	const POST_TYPE = 'et_pb_canvas';

	const QUERY_VAR_SAVED_PARENTS  = 'wpml_divi_canvas_saved_parents';
	const QUERY_VAR_SAVED_CONTEXTS = 'wpml_divi_canvas_saved_contexts';
	const QUERY_VAR_LIMIT          = 'wpml_divi_canvas_limit';

	private $sitepress;

	private $contextCanvases;

	private $isSyncingToServer = false;

	public function __construct( SitePress $sitepress, ContextCanvases $contextCanvases ) {
		$this->sitepress       = $sitepress;
		$this->contextCanvases = $contextCanvases;
	}

	public function add_hooks() {
		add_action( 'pre_get_posts', [ $this, 'handleCanvasQuery' ] );
		add_filter( 'posts_results', [ $this, 'keepCanvasesOfSavedParents' ], 10, 2 );
		add_filter( 'rest_pre_dispatch', [ $this, 'startSyncToServer' ], 10, 3 );
		add_filter( 'rest_request_after_callbacks', [ $this, 'endSyncToServer' ], 10, 3 );
		add_action( 'wpml_pro_translation_completed', [ $this, 'clearCachedCanvasesInParents' ] );
		add_action( 'wpml_pro_translation_completed', [ $this, 'translateContextOfCanvas' ] );
		add_action( 'icl_make_duplicate', [ $this, 'translateContextOfDuplicate' ], 10, 4 );
		add_action( 'wp_insert_post', [ $this, 'connectNewContextCanvas' ], 10, 3 );
	}

	public function startSyncToServer( $result, $server, $request ) {
		if (
			null === $result
			&& Editor::SYNC_ROUTE === $request->get_route()
			&& in_array( $request->get_method(), [ 'POST', 'PUT', 'PATCH' ], true )
		) {
			$this->isSyncingToServer = true;
		}

		return $result;
	}

	public function endSyncToServer( $response, $handler, $request ) {
		if ( Editor::SYNC_ROUTE === $request->get_route() ) {
			$this->isSyncingToServer = false;
		}

		return $response;
	}

	public function handleCanvasQuery( $query ) {
		if ( isset( $query->query_vars['post_type'] ) && self::POST_TYPE === $query->query_vars['post_type'] ) {
			$query->query_vars['suppress_filters'] = false;

			if ( ! empty( $query->query_vars['meta_query'] ) && is_array( $query->query_vars['meta_query'] ) ) {
				$savedParents                    = [];
				$savedContexts                   = [];
				$query->query_vars['meta_query'] = $this->translateClauses( $query->query_vars['meta_query'], $savedParents, $savedContexts );

				if ( ( $savedParents || $savedContexts ) && $this->isSyncingToServer ) {
					$this->prepareSaveLookup( $query, $savedParents, $savedContexts );
				}
			}
		}
	}

	private function prepareSaveLookup( \WP_Query $query, array $savedParents, array $savedContexts ) {
		$query->query_vars[ self::QUERY_VAR_SAVED_PARENTS ]  = $savedParents;
		$query->query_vars[ self::QUERY_VAR_SAVED_CONTEXTS ] = $savedContexts;

		if ( $this->hasTermContext( $savedContexts ) ) {
			$query->query['lang'] = 'all';
		}

		$fields = $query->query_vars['fields'] ?? '';
		$limit  = (int) ( $query->query_vars['posts_per_page'] ?? 0 );
		if ( $limit > 0 && ! in_array( $fields, [ 'ids', 'id=>parent' ], true ) ) {
			$query->query_vars[ self::QUERY_VAR_LIMIT ] = $limit;
			$query->query_vars['posts_per_page']        = -1;
			$query->query_vars['nopaging']              = true;
		}
	}

	private function hasTermContext( array $contexts ) {
		foreach ( $contexts as $context ) {
			if ( $this->contextCanvases->getLanguage( $context ) ) {
				return true;
			}
		}

		return false;
	}

	public function keepCanvasesOfSavedParents( $posts, $query ) {
		$savedParents  = $query->query_vars[ self::QUERY_VAR_SAVED_PARENTS ] ?? [];
		$savedContexts = $query->query_vars[ self::QUERY_VAR_SAVED_CONTEXTS ] ?? [];

		if ( ! is_array( $posts ) || ( empty( $savedParents ) && empty( $savedContexts ) ) ) {
			return $posts;
		}

		$isTranslatable = $this->sitepress->is_translated_post_type( self::POST_TYPE );

		if ( $savedParents ) {
			$posts = $this->keepCanvasesOfParents( $posts, $savedParents, $isTranslatable );
		}

		if ( $savedContexts ) {
			$posts = $this->keepCanvasesOfContexts( $posts, $savedContexts );
		}

		if ( ! empty( $query->query_vars[ self::QUERY_VAR_LIMIT ] ) ) {
			$posts = array_slice( $posts, 0, (int) $query->query_vars[ self::QUERY_VAR_LIMIT ] );
		}

		return $posts;
	}

	private function keepCanvasesOfParents( array $posts, array $savedParents, bool $isTranslatable ) {
		$parentsLanguages = [];

		foreach ( $savedParents as $savedParent ) {
			$language = $this->getPostLanguage( $savedParent );
			foreach ( $this->getAllTranslationIds( $savedParent ) as $translationId ) {
				$parentsLanguages[ (int) $translationId ][] = $language;
			}
		}

		$storedParents = $this->getStoredMeta( Lst::pluck( 'ID', $posts ), self::META_KEY );

		return array_values(
			array_filter(
				$posts,
				function ( $post ) use ( $savedParents, $isTranslatable, $parentsLanguages, $storedParents ) {
					return $this->isCanvasOfSavedParents(
						(int) $post->ID,
						(int) ( $storedParents[ (int) $post->ID ] ?? 0 ),
						$savedParents,
						$isTranslatable ? $parentsLanguages : []
					);
				}
			)
		);
	}

	private function keepCanvasesOfContexts( array $posts, array $savedContexts ) {
		$languages = array_map(
			function ( $savedContext ) {
				return $this->contextCanvases->getLanguage( $savedContext ) ?: $this->sitepress->get_current_language();
			},
			$savedContexts
		);

		return array_values(
			array_filter(
				$posts,
				function ( $post ) use ( $languages ) {
					return in_array( $this->sitepress->get_language_for_element( (int) $post->ID, LocalCanvasRepository::CANVAS_ELEMENT_TYPE ), $languages, true );
				}
			)
		);
	}

	private function isCanvasOfSavedParents( int $canvasId, int $storedParent, array $savedParents, array $parentsLanguages ) {
		if ( in_array( $storedParent, $savedParents, true ) ) {
			return true;
		}

		return isset( $parentsLanguages[ $storedParent ] )
			&& in_array(
				$this->sitepress->get_language_for_element( $canvasId, 'post_' . self::POST_TYPE ),
				$parentsLanguages[ $storedParent ],
				true
			);
	}

	private function getStoredMeta( array $canvasIds, string $key ) {
		$cache  = update_meta_cache( 'post', $canvasIds );
		$values = [];

		foreach ( $canvasIds as $canvasId ) {
			if ( isset( $cache[ $canvasId ][ $key ][0] ) ) {
				$values[ (int) $canvasId ] = (string) $cache[ $canvasId ][ $key ][0];
			}
		}

		return $values;
	}

	private function getPostLanguage( int $postId ) {
		$postType = get_post_type( $postId );

		return $postType ? $this->sitepress->get_language_for_element( $postId, 'post_' . $postType ) : null;
	}

	private function translateClauses( $clauses, &$savedParents, &$savedContexts ) {
		$isTranslatable = null;

		foreach ( $clauses as $index => $clause ) {
			if ( 'relation' !== $index && isset( $clause['key'] ) ) {
				if ( ContextCanvases::META_KEY === $clause['key'] ) {
					if ( null === $isTranslatable ) {
						$isTranslatable = $this->sitepress->is_translated_post_type( self::POST_TYPE );
					}
					if ( ! $isTranslatable ) {
						continue;
					}

					$expanded = $this->maybeExpandContextClause( $clause );

					if ( $this->isMatchClause( $clause ) ) {
						$savedContexts = array_values( array_unique( array_merge( $savedContexts, array_map( 'strval', (array) $clause['value'] ) ) ) );
					}
				} else {
					$expanded = $this->maybeExpandClause( $clause );

					if ( $expanded !== $clause ) {
						$savedParents = array_values( array_unique( array_merge( $savedParents, array_map( 'intval', (array) $clause['value'] ) ) ) );
					}
				}

				$clauses[ $index ] = $expanded;
			}
		}

		return $clauses;
	}

	private function maybeExpandClause( $clause ) {
		if ( self::META_KEY !== $clause['key'] || ! $this->isMatchClause( $clause ) ) {
			return $clause;
		}

		$allIds = [];

		foreach ( (array) $clause['value'] as $id ) {
			$allIds = array_merge( $allIds, $this->getAllTranslationIds( (int) $id ) );
		}

		$clause['value']   = array_values( array_unique( $allIds ) );
		$clause['compare'] = 'IN';

		return $clause;
	}

	private function maybeExpandContextClause( array $clause ) {
		if ( ! $this->isMatchClause( $clause ) ) {
			return $clause;
		}

		$keys    = array_values( array_map( 'strval', (array) $clause['value'] ) );
		$allKeys = [];
		foreach ( $keys as $key ) {
			$allKeys = array_merge( $allKeys, $this->contextCanvases->getAllKeys( $key ) );
		}
		$allKeys = array_values( array_unique( $allKeys ) );

		if ( $allKeys === $keys ) {
			return $clause;
		}

		$clause['value']   = $allKeys;
		$clause['compare'] = 'IN';

		return $clause;
	}

	private function isMatchClause( array $clause ) {
		return isset( $clause['value'] )
			&& [] !== $clause['value']
			&& in_array( strtoupper( $clause['compare'] ?? '=' ), [ '=', 'IN' ], true );
	}

	private function getAllTranslationIds( $id ) {
		if ( ! $id ) {
			return [ $id ];
		}

		$postType = get_post_type( $id );

		if ( ! $postType ) {
			return [ $id ];
		}

		$elementType = 'post_' . $postType;

		$trid = $this->sitepress->get_element_trid( $id, $elementType );

		if ( ! $trid ) {
			return [ $id ];
		}

		$translations = $this->sitepress->get_element_translations( $trid, $elementType, false, true );

		if ( empty( $translations ) ) {
			return [ $id ];
		}

		return Lst::pluck( 'element_id', array_values( $translations ) );
	}

	public function connectNewContextCanvas( $postId, $post, $update ) {
		if (
			(int) $postId <= 0
			|| ! $post instanceof \WP_Post
			|| $update
			|| ! $this->isSyncingToServer
			|| self::POST_TYPE !== $post->post_type
			|| ! $this->sitepress->is_translated_post_type( self::POST_TYPE )
		) {
			return;
		}

		$key      = (string) get_post_meta( $postId, ContextCanvases::META_KEY, true );
		$canvasId = (string) get_post_meta( $postId, LocalCanvasRepository::META_CANVAS_ID, true );
		$language = $this->sitepress->get_language_for_element( $postId, LocalCanvasRepository::CANVAS_ELEMENT_TYPE );

		if ( ! $key || ! $canvasId || ! $language ) {
			return;
		}

		$contextLanguage = $this->contextCanvases->getLanguage( $key ) ?: $language;
		$original        = $this->contextCanvases->findOriginalToConnect( (int) $postId, $canvasId, $key, $contextLanguage );

		if ( $original ) {
			$this->sitepress->set_element_language_details(
				$postId,
				LocalCanvasRepository::CANVAS_ELEMENT_TYPE,
				(int) $original->trid,
				$contextLanguage,
				$original->language_code
			);
		} elseif ( $contextLanguage !== $language ) {
			$this->sitepress->set_element_language_details(
				$postId,
				LocalCanvasRepository::CANVAS_ELEMENT_TYPE,
				$this->sitepress->get_element_trid( $postId, LocalCanvasRepository::CANVAS_ELEMENT_TYPE ),
				$contextLanguage
			);
		}
	}

	public function translateContextOfCanvas( $postId ) {
		if ( self::POST_TYPE !== get_post_type( $postId ) ) {
			return;
		}

		$originalId = (int) $this->sitepress->get_original_element_id( $postId, LocalCanvasRepository::CANVAS_ELEMENT_TYPE );

		if ( $originalId && $originalId !== (int) $postId ) {
			$this->copyTranslatedContext( $originalId, (int) $postId );
		}
	}

	public function translateContextOfDuplicate( $masterPostId, $language, $postArray, $duplicateId ) {
		if ( self::POST_TYPE === get_post_type( $masterPostId ) ) {
			$this->copyTranslatedContext( (int) $masterPostId, (int) $duplicateId );
		}
	}

	private function copyTranslatedContext( int $originalId, int $translationId ) {
		$key = (string) get_post_meta( $originalId, ContextCanvases::META_KEY, true );

		if ( ! $key ) {
			delete_post_meta( $translationId, ContextCanvases::META_KEY );
			return;
		}

		$language = $this->sitepress->get_language_for_element( $translationId, LocalCanvasRepository::CANVAS_ELEMENT_TYPE );
		if ( $language ) {
			update_post_meta( $translationId, ContextCanvases::META_KEY, $this->contextCanvases->translate( $key, $language ) );
		}
	}

	public function clearCachedCanvasesInParents( $postId ) {
		if ( self::POST_TYPE === get_post_type( $postId ) ) {
			$parent = get_post_meta( $postId, self::META_KEY, true );

			if ( ! $parent ) {
				$parent = 'all';
			}

			if ( class_exists( '\ET_Core_PageResource' ) && method_exists( '\ET_Core_PageResource', 'clear_post_meta_caches' ) ) {
				\ET_Core_PageResource::clear_post_meta_caches( $parent );
			}
		}
	}
}
