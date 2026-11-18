<?php

class WPML_URL_Cached_Converter extends WPML_URL_Converter {

	const CACHE_GROUP = 'convert_url';

	const CACHE_KEY_VERSION = 4;

	private $cache;

	private $languages_of_urls = [];

	public function convert_url( $url, $lang_code = false ) {
		global $sitepress;

		if ( ! $lang_code ) {
			$lang_code = $sitepress->get_current_language();
		}

		if (
			$url
			&& $this->get_language_to_convert_from( $url ) === $lang_code
			&& $this->keeps_same_language_url( $lang_code )
		) {
			return $url;
		}

		$negotiation_type = $sitepress->get_setting( 'language_negotiation_type' );

		if ( intval( $negotiation_type ) === WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY ) {
			$urls = $sitepress->get_setting( 'urls' );
			$negotiation_type = ! empty( $urls['directory_for_default_language'] )
				? 11
				: $negotiation_type;
		}

		$skip_convert_url_string = $this->get_strategy()->skip_convert_url_string( $url, $lang_code );

		$cache_key_args = array( $url, $lang_code, $negotiation_type, $skip_convert_url_string, self::CACHE_KEY_VERSION );

		$strategy       = $this->get_strategy();
		$root_url_state = $strategy instanceof WPML_URL_Converter_Subdir_Strategy ? $strategy->get_root_url_state() : '';
		if ( '' !== $root_url_state ) {
			$cache_key_args[] = $root_url_state;
		}

		$cache_key      = md5( (string) wp_json_encode( $cache_key_args ) );
		$cache_found    = false;
		$cache          = new WPML_WP_Cache( self::CACHE_GROUP );
		$new_url        = $cache->get( $cache_key, $cache_found );

		if ( ! $cache_found ) {
			$new_url = parent::convert_url( $url, $lang_code );
			$cache->set( $cache_key, $new_url );
		}

		return $new_url;
	}

	public function get_language_from_url( $url ) {
		if ( isset( $this->cache[ $url ] ) ) {
			return $this->cache[ $url ];
		}

		$lang = parent::get_language_from_url( $url );

		$this->cache[ $url ] = $lang;

		return $lang;
	}

	public function get_language_of_url( $url ) {
		if ( ! array_key_exists( $url, $this->languages_of_urls ) ) {
			$this->languages_of_urls[ $url ] = parent::get_language_of_url( $url );
		}

		return $this->languages_of_urls[ $url ];
	}
}
