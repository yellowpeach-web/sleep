<?php

namespace WPML\QueryFiltering;

class ElementTranslationPrefetcher implements \IWPML_Action, \IWPML_Frontend_Action_Loader, \IWPML_Backend_Action_Loader {

	private $post_translations;

	public function __construct( ?\WPML_Post_Translation $postTranslations = null ) {
		$this->post_translations = $postTranslations;
	}

	public function create() {
		return $this;
	}

	public function add_hooks(): void {
		add_filter( 'the_posts', [ $this, 'prefetchResultSet' ], 10, 1 );
		add_filter( 'get_pages', [ $this, 'prefetchResultSet' ], 10, 1 );
		if ( ! is_admin() ) {
			add_filter( 'update_post_metadata_cache', [ $this, 'prefetchPrimedPosts' ], 10, 2 );
		}
	}

	public function prefetchPrimedPosts( $check, $postIds ) {
		$post_translations = $this->postTranslations();
		if ( null !== $check || ! is_array( $postIds ) || count( $postIds ) < 2 || ! $post_translations ) {
			return $check;
		}

		$ids            = [];
		$prefetchesType = [];
		foreach ( $postIds as $postId ) {
			$postId = (int) $postId;
			if ( $postId < 1 ) {
				continue;
			}
			$post = wp_cache_get( $postId, 'posts' );
			if ( ! is_object( $post ) || empty( $post->post_type ) ) {
				$ids[] = $postId;
				continue;
			}
			$type = $post->post_type;
			if ( ! isset( $prefetchesType[ $type ] ) ) {
				$prefetchesType[ $type ] = is_post_type_viewable( $type ) && apply_filters( 'wpml_is_translated_post_type', false, $type );
			}
			if ( $prefetchesType[ $type ] ) {
				$ids[] = $postId;
			}
		}

		if ( count( $ids ) > 1 ) {
			$post_translations->prefetch_ids( $ids );
		}

		return $check;
	}

	public function prefetchResultSet( $posts ) {
		$ids               = array_filter( array_map( 'intval', wp_list_pluck( (array) $posts, 'ID' ) ) );
		$post_translations = $this->postTranslations();
		if ( $ids && $post_translations ) {
			$post_translations->prefetch_ids( $ids );
		}

		return $posts;
	}

	private function postTranslations() {
		if ( $this->post_translations ) {
			return $this->post_translations;
		}
		global $wpml_post_translations;

		return $wpml_post_translations;
	}
}
