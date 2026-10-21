<?php

abstract class WPML_URL_Converter_Abstract_Strategy implements IWPML_URL_Converter_Strategy {
	protected $absolute_home;

	protected $default_language;
	protected $active_languages;
	protected $language_codes_map;
	protected $language_codes_reverse_map;

	protected $cache;

	protected $url_helper;

	protected $lang_param;

	protected $slash_helper;

	protected $wp_rewrite;

	public function __construct( $default_language, $active_languages, $wp_rewrite = null, $splash_helper = null ) {
		$this->default_language = $default_language;
		$this->active_languages = $active_languages;
		$this->language_codes_map = (array) array_combine( $active_languages, $active_languages );
		$this->language_codes_map = apply_filters( 'wpml_language_codes_map', $this->language_codes_map );
		$this->language_codes_reverse_map = array_flip( $this->language_codes_map );

		$this->lang_param   = new WPML_URL_Converter_Lang_Param_Helper( $active_languages );
		$this->slash_helper = $splash_helper ?: new WPML_Slash_Management();

		if ( ! $wp_rewrite ) {
			global $wp_rewrite;
		}
		$this->wp_rewrite = $wp_rewrite;
	}

	public function validate_language( $language, $url ) {
		$language = $this->get_canonical_language_code( $language );
		return in_array( $language, $this->active_languages, true )
			   || 'all' === $language && $this->get_url_helper()->is_url_admin( $url ) ? $language : $this->get_default_language();
	}

	public function set_url_helper( WPML_URL_Converter_Url_Helper $url_helper ) {
		$this->url_helper = $url_helper;
	}

	public function get_url_helper() {
		if ( ! $this->url_helper ) {
			$this->url_helper = new WPML_URL_Converter_Url_Helper();
		}

		return $this->url_helper;
	}

	public function set_lang_param( WPML_URL_Converter_Lang_Param_Helper $lang_param ) {
		$this->lang_param = $lang_param;
	}

	public function set_slash_helper( WPML_Slash_Management $slash_helper ) {
		$this->slash_helper = $slash_helper;
	}

	protected function split_fragment( $url ) {
		$position = is_string( $url ) ? strpos( $url, '#' ) : false;

		return false === $position
			? [ $url, '' ]
			: [ substr( $url, 0, $position ), substr( $url, $position ) ];
	}

	private function get_default_language() {
		if ( $this->default_language ) {
			return $this->default_language;
		} else {
			return icl_get_setting( 'default_language' );
		}
	}

	public function fix_trailingslashit( $source_url ) {
		return $source_url;
	}

	public function skip_convert_url_string( $source_url, $lang_code ) {
		return apply_filters( 'wpml_skip_convert_url_string', false, $source_url, $lang_code );
	}

	public function use_wp_login_url_converter() {
		return false;
	}

	protected function get_url_language_code( $language ) {
		return isset( $this->language_codes_map[ $language ] )
			? $this->language_codes_map[ $language ]
			: $language;
	}

	protected function get_canonical_language_code( $language ) {
		if ( null === $language ) {
			return null;
		}

		return isset( $this->language_codes_reverse_map[ $language ] )
			? $this->language_codes_reverse_map[ $language ]
			: $language;
	}
}
