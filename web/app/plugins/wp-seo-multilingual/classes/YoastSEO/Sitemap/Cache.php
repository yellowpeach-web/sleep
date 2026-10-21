<?php

namespace WPML\WPSEO\YoastSEO\Sitemap;

use WPML\Settings\LanguageNegotiation;

class Cache implements \IWPML_Frontend_Action {

	public function add_hooks() {
		if ( ! LanguageNegotiation::isDomain() ) {
			return;
		}

		if ( ! class_exists( '\WPSEO_Sitemaps_Cache_Validator' ) ) {
			add_filter( 'wpseo_enable_xml_sitemap_transient_caching', '__return_false' );

			return;
		}

		add_action( 'pre_get_posts', [ $this, 'intercept' ], 0 );
	}

	public function intercept( $query ) {
		if ( ! $query->is_main_query() || get_query_var( 'yoast-sitemap-xsl' ) ) {
			return;
		}

		$type = get_query_var( 'sitemap' );
		if ( empty( $type ) ) {
			return;
		}

		$page = get_query_var( 'sitemap_n' );
		if ( (int) $page < 2 ) {
			$page = '1';
		}

		$transient = \WPSEO_Sitemaps_Cache_Validator::get_storage_key( $type, $page );

		add_filter( "pre_set_transient_{$transient}", [ $this, 'setTransient' ], 10, 3 );
		add_filter( "pre_transient_{$transient}", [ $this, 'getTransient' ], 10, 2 );
	}

	public function setTransient( $value, $expiration, $transient ) {
		$key = $this->getKeyWithLanguage( $transient );

		set_transient( $key, $value, $expiration );

		return false;
	}

	public function getTransient( $value, $transient ) {
		$key = $this->getKeyWithLanguage( $transient );

		return get_transient( $key );
	}

	private function getKeyWithLanguage( $key ) {
		return $key . ':' . apply_filters( 'wpml_current_language', false );
	}
}
