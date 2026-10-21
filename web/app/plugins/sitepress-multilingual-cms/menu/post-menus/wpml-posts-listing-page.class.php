<?php

use WPML\Plugins;
use WPML\Posts\ListScreenRows;

class WPML_Posts_Listing_Page {
	public function __construct() {
		global $pagenow;

		if ( ! Plugins::isTMLoadedForRequest() ) {
			// Blog License. No extra preload required as Review is not
			return;
		}

		if ( 'edit.php' !== $pagenow ) {
			return;
		}

		add_action( 'wp', [ $this, 'pre_populate_caches' ] );
	}

	public function pre_populate_caches() {
		global $wp_query;

		if ( ! $wp_query->posts ) {
			return;
		}

		if ( ListScreenRows::arePartial( $wp_query->posts ) ) {
			add_action( 'the_post', [ $this, 'pre_populate_screen_caches' ] );

			return;
		}

		$this->preload( $wp_query->posts );
	}

	public function pre_populate_screen_caches() {
		global $wp_query;

		remove_action( 'the_post', [ $this, 'pre_populate_screen_caches' ] );

		if ( is_array( $wp_query->posts ) ) {
			$this->preload( ListScreenRows::of( $wp_query->posts ) );
		}
	}

	private function preload( array $posts ) {
		global $wpml_post_translations;

		if ( ! $posts ) {
			return;
		}

		$post_ids = array_map( function( $post ) {
			return $post->ID;
		}, $posts );

		$wpml_post_translations->prefetch_ids( $post_ids );

		$wpml_tm_element_translations = wpml_tm_load_element_translations();
		$wpml_tm_element_translations->init_jobs( $wpml_post_translations->get_trids() );
	}
}
