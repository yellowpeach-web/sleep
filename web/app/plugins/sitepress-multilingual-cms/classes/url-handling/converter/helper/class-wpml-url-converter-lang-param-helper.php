<?php

use WPML\FP\Str;

class WPML_URL_Converter_Lang_Param_Helper {
	private $cache = array();

	private $active_languages;
	private $language_codes_reverse_map;

	public function __construct( array $active_languages ) {
		$this->active_languages = $active_languages;
		$code_map = (array) array_combine( $active_languages, $active_languages );
		$code_map = apply_filters( 'wpml_language_codes_map', $code_map );
		$this->language_codes_reverse_map = array_flip( $code_map );
	}

	private function resolve_active_language_from_locale( $locale ) {
		$variant = strtolower( str_replace( '_', '-', $locale ) );
		if ( in_array( $variant, $this->active_languages, true ) ) {
			return $variant;
		}

		$parts = explode( '_', $locale );
		$base  = strtolower( $parts[0] );

		return in_array( $base, $this->active_languages, true ) ? $base : null;
	}

	public function lang_by_param( $url, $only_admin = true ) {
		$cache_key = ( $only_admin ? 'admin:' : 'all:' ) . $url;

		if ( array_key_exists( $cache_key, $this->cache ) ) {
			return $this->cache[ $cache_key ];
		}

		$lang = $this->extract_lang_param_from_url( $url, $only_admin );

		$this->cache[ $cache_key ] = $lang;

		return $lang;
	}

	private function extract_lang_param_from_url( $url, $only_admin ) {
		$url             = wpml_strip_subdir_from_url( $url );
		$url_query_parts = wpml_parse_url( $url );
		$url_query       = $this->has_query_part( $only_admin, $url_query_parts ) ? untrailingslashit( $url_query_parts['query'] ) : null;
		$isLoginPageUrl  = Str::includes( 'wp-login.php', $_SERVER['REQUEST_URI'] );
		$isLoginPage     = function( $vars ) use ( $isLoginPageUrl ) {
			return $isLoginPageUrl && isset( $vars['wp_lang'] ) && is_string( $vars['wp_lang'] );
		};
		$getWpLang       = function( $vars ) {
			return $this->resolve_active_language_from_locale( (string) $vars['wp_lang'] );
		};

		if ( null !== $url_query ) {
			parse_str( $url_query, $vars );
			if ( $this->can_retrieve_lang_from_query( $only_admin, $vars ) ) {
				return $this->get_canonical_language_code( $vars['lang'] );
			} else if ( $isLoginPage( $vars ) ) {
				$wp_lang_code = $getWpLang( $vars );
				if ( null !== $wp_lang_code ) {
					return $wp_lang_code;
				}
			}
		}

		if ( is_array( $url_query_parts ) && isset( $url_query_parts['query'] ) && is_string( $url_query_parts['query'] ) ) {
			parse_str( $url_query_parts['query'], $vars );
			if ( $isLoginPage( $vars ) ) {
				return $getWpLang( $vars );
			}
		}

		return null;
	}

	private function has_query_part( $only_admin, $url_query_parts ) {
		if ( ! isset( $url_query_parts['query'] ) ) {
			return false;
		}

		if ( false === $only_admin ) {
			return true;
		}

		if ( ! isset( $url_query_parts['path'] ) ) {
			return false;
		}

		if ( false === strpos( $url_query_parts['path'], '/wp-admin' ) ) {
			return false;
		}

		return true;
	}

	private function can_retrieve_lang_from_query( $only_admin, $vars ) {
		if ( ! isset( $vars['lang'] ) ) {
			return false;
		}

		if ( $only_admin && 'all' === $vars['lang'] ) {
			return true;
		}

		if (
			in_array( $vars['lang'], $this->active_languages, true )
			|| isset( $this->language_codes_reverse_map[ $vars['lang'] ] )
		) {
			return true;
		}

		return false;
	}

	private function get_canonical_language_code( $language ) {
		return isset( $this->language_codes_reverse_map[ $language ] )
			? $this->language_codes_reverse_map[ $language ]
			: $language;
	}
}
