<?php

class WPML_WP_In_Subdir_URL_Filters implements IWPML_Action {

	private $backtrace;

	private $sitepress;

	private $url_converter;

	private $uri_without_subdir;

	public function __construct(
		WPML_Debug_BackTrace $backtrace,
		SitePress $sitepress,
		WPML_URL_Converter $url_converter,
		$uri_without_subdir
	) {
		$this->url_converter      = $url_converter;
		$this->backtrace          = $backtrace;
		$this->sitepress          = $sitepress;
		$this->url_converter      = $url_converter;
		$this->uri_without_subdir = $uri_without_subdir;
	}

	public function add_hooks() {
		add_filter( 'home_url', array( $this, 'home_url_filter_on_parse_request' ), PHP_INT_MAX );
	}

	public function home_url_filter_on_parse_request( $home_url ) {

		if ( $this->backtrace->is_class_function_in_call_stack( 'WP', 'parse_request' ) ) {

			if ( $this->request_uri_begins_with_lang() ) {
				return $home_url;
			}

			return $this->url_converter->get_abs_home();
		}

		return $home_url;
	}

	private function request_uri_begins_with_lang() {
		$uri_parts = explode( '/', trim( wpml_parse_url( $this->uri_without_subdir, PHP_URL_PATH ), '/' ) );

		return isset( $uri_parts[0] ) && in_array( $uri_parts[0], $this->get_url_language_codes(), true );
	}

	private function get_url_language_codes() {
		$active_lang_codes = array_keys( $this->sitepress->get_active_languages() );
		$map               = apply_filters(
			'wpml_language_codes_map',
			array_combine( $active_lang_codes, $active_lang_codes )
		);

		return is_array( $map ) ? array_values( $map ) : $active_lang_codes;
	}
}
