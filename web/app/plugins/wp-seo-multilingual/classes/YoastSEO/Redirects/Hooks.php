<?php

namespace WPML\WPSEO\YoastSEO\Redirects;

use WPML\Settings\LanguageNegotiation;

class Hooks implements \IWPML_Backend_Action, \IWPML_REST_Action {

	const AJAX_ADD_REDIRECT_PRIORITY    = 10;
	const AJAX_UPDATE_REDIRECT_PRIORITY = 10;

	const DETECT_SLUG_CHANGE_PRIORITY = 12;

	const REDIRECT_REST_WRITE_ROUTES = [ '/yoast/v1/redirects', '/yoast/v1/redirects/update' ];

	public function add_hooks() {
		if ( ! LanguageNegotiation::isDir() ) {
			return;
		}

		add_filter( 'rest_request_before_callbacks', [ $this, 'disableHomeUrlFilterOnRedirectRest' ], 10, 3 );
		add_filter( 'rest_request_after_callbacks', [ $this, 'restoreHomeUrlFilterOnRedirectRest' ], 10, 3 );

		if ( ! is_admin() ) {
			return;
		}

		$this->loadFiltersOn( 'wp_ajax_wpseo_add_redirect_plain', self::AJAX_ADD_REDIRECT_PRIORITY );
		$this->loadFiltersOn( 'wp_ajax_wpseo_add_redirect_regex', self::AJAX_ADD_REDIRECT_PRIORITY );
		$this->loadFiltersOn( 'wp_ajax_wpseo_update_redirect_plain', self::AJAX_UPDATE_REDIRECT_PRIORITY );
		$this->loadFiltersOn( 'wp_ajax_wpseo_update_redirect_regex', self::AJAX_UPDATE_REDIRECT_PRIORITY );
		$this->loadFiltersOn( 'post_updated', self::DETECT_SLUG_CHANGE_PRIORITY );
	}

	private function loadFiltersOn( $hook, $priority ) {
		add_action( $hook, [ $this, 'disableHomeUrlFilter' ], $priority - 1 );
		add_action( $hook, [ $this, 'restoreHomeUrlFilter' ], $priority + 1 );
	}

	public function disableHomeUrlFilter() {
		add_filter( 'wpml_get_home_url', [ $this, 'overwriteHomeUrl' ], 10, 2 );
	}

	public function restoreHomeUrlFilter() {
		remove_filter( 'wpml_get_home_url', [ $this, 'overwriteHomeUrl' ], 10 );
	}

	public function disableHomeUrlFilterOnRedirectRest( $response, $handler, $request ) {
		if ( $this->isRedirectWriteRoute( $request ) ) {
			$this->disableHomeUrlFilter();
		}

		return $response;
	}

	public function restoreHomeUrlFilterOnRedirectRest( $response, $handler, $request ) {
		if ( $this->isRedirectWriteRoute( $request ) ) {
			$this->restoreHomeUrlFilter();
		}

		return $response;
	}

	private function isRedirectWriteRoute( $request ) {
		return $request instanceof \WP_REST_Request
			&& in_array( $request->get_route(), self::REDIRECT_REST_WRITE_ROUTES, true );
	}

	public function overwriteHomeUrl( $homeUrl, $url ) {
		return $url;
	}
}
