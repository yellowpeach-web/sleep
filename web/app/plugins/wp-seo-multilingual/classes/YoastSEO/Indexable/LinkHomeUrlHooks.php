<?php

namespace WPML\WPSEO\YoastSEO\Indexable;

use Yoast\WP\SEO\Routes\Indexing_Route;

class LinkHomeUrlHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const PRIORITY_BEFORE_YOAST_WATCHERS = PHP_INT_MAX - 1;
	const PRIORITY_FIRST                 = PHP_INT_MIN;
	const PRIORITY_LAST                  = PHP_INT_MAX;

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		if ( ! $this->hasLanguageDirectoriesInHomeUrl() ) {
			return;
		}

		add_action( 'wp_insert_post', [ $this, 'useSiteHomeUrl' ], self::PRIORITY_BEFORE_YOAST_WATCHERS );
		add_action( 'wp_after_insert_post', [ $this, 'useLanguageHomeUrlUntilShutdown' ], self::PRIORITY_FIRST );
		add_action( 'created_term', [ $this, 'useSiteHomeUrl' ], self::PRIORITY_BEFORE_YOAST_WATCHERS );
		add_action( 'edited_term', [ $this, 'useSiteHomeUrl' ], self::PRIORITY_BEFORE_YOAST_WATCHERS );
		add_action( 'saved_term', [ $this, 'useLanguageHomeUrl' ], self::PRIORITY_FIRST );

		if ( class_exists( Indexing_Route::class ) ) {
			add_filter( 'rest_request_before_callbacks', [ $this, 'useSiteHomeUrlForLinkIndexing' ], 10, 3 );
		}
		add_filter( 'rest_request_after_callbacks', [ $this, 'useLanguageHomeUrlAfterRestCallback' ] );
	}

	public function useSiteHomeUrl() {
		add_filter( 'wpml_get_home_url', [ $this, 'keepSiteHomeUrl' ], self::PRIORITY_LAST, 2 );
	}

	public function useLanguageHomeUrl() {
		remove_filter( 'wpml_get_home_url', [ $this, 'keepSiteHomeUrl' ], self::PRIORITY_LAST );
	}

	public function useLanguageHomeUrlUntilShutdown() {
		$this->useLanguageHomeUrl();
		add_action( 'shutdown', [ $this, 'useSiteHomeUrl' ], self::PRIORITY_LAST );
	}

	public function keepSiteHomeUrl( $languageHomeUrl, $siteHomeUrl ) {
		return $siteHomeUrl;
	}

	public function useSiteHomeUrlForLinkIndexing( $response, $handler, $request ) {
		if ( $this->isLinkIndexingRequest( $request ) ) {
			$this->useSiteHomeUrl();
		}

		return $response;
	}

	public function useLanguageHomeUrlAfterRestCallback( $response ) {
		$this->useLanguageHomeUrl();

		return $response;
	}

	private function isLinkIndexingRequest( \WP_REST_Request $request ) {
		$linkIndexingRoutes = [
			Indexing_Route::FULL_POST_LINKS_INDEXING_ROUTE,
			Indexing_Route::FULL_TERM_LINKS_INDEXING_ROUTE,
		];

		return in_array( ltrim( $request->get_route(), '/' ), $linkIndexingRoutes, true );
	}

	private function hasLanguageDirectoriesInHomeUrl() {
		return WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY === (int) $this->sitepress->get_setting( 'language_negotiation_type' );
	}
}
