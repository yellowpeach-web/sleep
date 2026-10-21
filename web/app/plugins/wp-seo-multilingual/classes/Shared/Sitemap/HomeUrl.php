<?php

namespace WPML\WPSEO\Shared\Sitemap;

class HomeUrl {

	public static function get( $language ) {
		return (string) apply_filters( 'wpml_permalink', home_url( '/' ), $language );
	}
}
