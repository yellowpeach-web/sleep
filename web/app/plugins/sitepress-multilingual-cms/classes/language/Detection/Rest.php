<?php

namespace WPML\Language\Detection;

use \WPML_Request;

class Rest extends WPML_Request {
	private $backend;

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
			?: $this->backend->get_requested_lang_from_params()
			?: $this->get_referer_lang()
			?: $this->get_parameter_mode_frontend_referer_lang()
			?: $this->backend->get_cookie_lang();
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
