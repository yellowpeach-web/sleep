<?php

namespace WPML\PB\Elementor\Hooks;

class RecaptchaLanguage implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const HANDLES = [
		'elementor-recaptcha-api',
		'elementor-recaptcha_v3-api',
	];

	public function add_hooks() {
		add_filter( 'script_loader_src', [ $this, 'addLanguage' ], 10, 2 );
	}

	public function addLanguage( $src, $handle ) {
		if ( ! is_string( $src ) || ! in_array( $handle, self::HANDLES, true ) || $this->hasLanguage( $src ) ) {
			return $src;
		}

		$language = apply_filters( 'wpml_current_language', null );

		if ( ! is_string( $language ) || '' === $language ) {
			return $src;
		}

		return add_query_arg( 'hl', $this->googleCode( $language ), $src );
	}

	private function hasLanguage( $src ) {
		$query = wp_parse_url( $src, PHP_URL_QUERY );

		if ( ! is_string( $query ) ) {
			return false;
		}

		parse_str( $query, $args );

		return isset( $args['hl'] );
	}

	private function googleCode( $language ) {
		$parts = explode( '-', $language, 2 );

		return 2 === count( $parts ) ? $parts[0] . '-' . strtoupper( $parts[1] ) : $language;
	}
}
