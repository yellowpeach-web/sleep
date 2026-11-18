<?php

namespace WPML\Language\Detection;

use \WPML_Request;
use WPML\Compatibility\TheEventsCalendar\ViewRestRoute;

class Rest extends WPML_Request {
	private const VIEW_URL_PARAMS = [
		ViewRestRoute::ROUTE => ViewRestRoute::URL_PARAM,
	];

	private $backend;

	private $is_language_from_view = false;

	public function __construct(
		$url_converter,
		$active_languages,
		$default_language,
		$cookieLanguage,
		$backend
	) {
		parent::__construct( $url_converter, $active_languages, $default_language, $cookieLanguage );
		$this->backend = $backend;
	}


	protected function get_cookie_name() {
		return $this->cookieLanguage->getAjaxCookieName( ! $this->getFrontendLanguage() );
	}

	public function get_requested_lang() {
		return $this->getFrontendLanguage()
			?: $this->get_view_url_lang()
			?: $this->backend->get_requested_lang_from_params()
			?: $this->get_referer_lang()
			?: $this->get_parameter_mode_frontend_referer_lang()
			?: $this->backend->get_cookie_lang();
	}

	private function get_view_url_lang() {
		$this->is_language_from_view = false;

		$param = $this->get_view_url_param();
		if ( null === $param ) {
			return null;
		}

		$url = filter_input( INPUT_GET, $param, FILTER_SANITIZE_URL ) ?: filter_input( INPUT_POST, $param, FILTER_SANITIZE_URL );
		if ( ! is_string( $url ) || '' === $url ) {
			return null;
		}

		$language = $this->url_converter->get_language_from_url( $url );
		if ( ! is_string( $language ) || ! in_array( $language, $this->active_languages, true ) ) {
			return null;
		}

		$this->is_language_from_view = true;

		return $language;
	}

	public function is_language_from_referer() {
		return $this->is_language_from_view || parent::is_language_from_referer();
	}

	private function get_view_url_param() {
		$route = filter_input( INPUT_GET, 'rest_route', FILTER_SANITIZE_URL );
		if ( ! is_string( $route ) || '' === $route ) {
			$route = (string) strtok( $this->get_request_uri( FILTER_SANITIZE_URL ), '?' );
		}

		$route      = rtrim( $route, '/' );
		$rest_route = '/' . rest_get_url_prefix();
		foreach ( self::VIEW_URL_PARAMS as $view_route => $param ) {
			if ( $route === $view_route || substr( $route, -strlen( $rest_route . $view_route ) ) === $rest_route . $view_route ) {
				return $param;
			}
		}

		return null;
	}

	private function get_parameter_mode_frontend_referer_lang() {
		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_PARAMETER !== (int) apply_filters( 'wpml_setting', 0, 'language_negotiation_type' ) ) {
			return null;
		}

		if ( in_array( $this->get_request_method(), [ 'GET', 'HEAD' ], true ) ) {
			return null;
		}

		return $this->get_frontend_referer_lang();
	}

	private function get_request_method() {
		return isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			: 'GET';
	}

	private function getFrontendLanguage() {
		$language = $this->get_request_uri_lang();

		return $language && $language !== $this->default_language ? $language : null;
	}
}
