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
