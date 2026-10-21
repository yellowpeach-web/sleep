<?php

class WPML_REST_Request_Analyze {

	private $url_converter;

	private $active_language_codes;

	private $wp_rewrite;

	private $uri_parts;

	private $read_rewrite_rules;

	private $api_prefixes_from_rewrite_rules;

	private static $reading_rewrite_rules = false;

	public function __construct(
		WPML_URL_Converter $url_converter,
		array $active_language_codes,
		WP_Rewrite $wp_rewrite,
		$read_rewrite_rules = null
	) {
		$this->url_converter         = $url_converter;
		$this->active_language_codes = $active_language_codes;
		$this->wp_rewrite            = $wp_rewrite;
		$this->read_rewrite_rules    = is_callable( $read_rewrite_rules ) ? $read_rewrite_rules : null;
	}

	public function is_rest_request() {
		if ( array_key_exists( 'rest_route', $_REQUEST ) ) {
			return true;
		}

		$uri_part = $this->get_uri_part( $this->has_valid_language_prefix() ? 1 : 0 );

		if ( '' === $uri_part ) {
			return false;
		}

		return in_array( $uri_part, $this->get_api_prefixes(), true );
	}

	private function get_api_prefixes() {
		$prefixes = array( $this->get_filtered_rest_prefix() );

		foreach ( $this->get_api_prefixes_from_rewrite_rules() as $prefix ) {
			$prefixes[] = $prefix;
		}

		return array_values( array_unique( array_filter( $prefixes ) ) );
	}

	private function get_filtered_rest_prefix() {
		$prefix = 'wp-json';

		if ( function_exists( 'rest_get_url_prefix' ) ) {
			$prefix = (string) rest_get_url_prefix();
		}

		return trim( $prefix, '/' );
	}

	private function get_api_prefixes_from_rewrite_rules() {
		if ( null !== $this->api_prefixes_from_rewrite_rules ) {
			return $this->api_prefixes_from_rewrite_rules;
		}

		if ( self::$reading_rewrite_rules ) {
			return array();
		}

		$this->api_prefixes_from_rewrite_rules = array();

		if ( null === $this->read_rewrite_rules ) {
			return $this->api_prefixes_from_rewrite_rules;
		}

		self::$reading_rewrite_rules = true;
		try {
			$rules = call_user_func( $this->read_rewrite_rules );
		} finally {
			self::$reading_rewrite_rules = false;
		}

		if ( ! is_array( $rules ) ) {
			return $this->api_prefixes_from_rewrite_rules;
		}

		$prefixes = array();

		foreach ( $rules as $key => $target ) {
			if ( ! is_string( $key ) || ! is_string( $target ) ) {
				continue;
			}

			if ( false === strpos( $target, 'rest_route=' ) ) {
				continue;
			}

			if ( preg_match( '#^\^(?:\([^)]*\)\?)?([^/^$()\[\]|.*+?\\\\]+)/#', $key, $matches ) ) {
				$prefixes[] = $matches[1];
			}
		}

		$this->api_prefixes_from_rewrite_rules = array_values( array_unique( $prefixes ) );

		return $this->api_prefixes_from_rewrite_rules;
	}

	private function has_valid_language_prefix() {
		if ( $this->url_converter->get_strategy() instanceof WPML_URL_Converter_Subdir_Strategy ) {
			$maybe_lang = $this->get_uri_part();
			$code_map   = (array) array_combine( $this->active_language_codes, $this->active_language_codes );
			$code_map   = apply_filters( 'wpml_language_codes_map', $code_map );

			return in_array( $maybe_lang, $code_map, true );
		}

		return false;
	}

	private function get_uri_part( $index = 0 ) {
		if ( null === $this->uri_parts ) {
			$request_uri = (string) filter_var( $_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL );
			$cleaned_uri = ltrim( wpml_strip_subdir_from_url( $request_uri ), '/' );

			if ( $this->wp_rewrite->using_index_permalinks() ) {
				$cleaned_uri = preg_replace( '/^' . preg_quote( $this->wp_rewrite->index, '/' ) . '\//', '', $cleaned_uri, 1 );
			}

			$this->uri_parts = explode( '/', $cleaned_uri );
		}

		return isset( $this->uri_parts[ $index ] ) ? $this->uri_parts[ $index ] : '';
	}
}
