<?php

namespace WPML\Compatibility\YoastSEO;

class SitemapPermalinks implements \IWPML_Frontend_Action {

	public function add_hooks() {
		add_filter( 'wpseo_build_sitemap_post_type', [ $this, 'keepOwnPermalinks' ] );
	}

	public function keepOwnPermalinks( $type ) {
		add_filter( 'wpml_use_permalink_of_post_translation', '__return_false' );

		return $type;
	}
}
