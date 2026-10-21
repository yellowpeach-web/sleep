<?php

use WPML\WPSEO\YoastSEO\Utils;

class WPML_WPSEO_Redirection {

	const OPTION = 'wpseo-premium-redirects-base';

	const FORMAT_REGEX = 'regex';

	public function is_redirection() {
		if ( ! Utils::isPremium() ) {
			return false;
		}

		$redirections = $this->get_all_redirections();
		if ( ! is_array( $redirections ) ) {
			return false;
		}

		$requestUri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

		$url = trim( $requestUri, '/' );

		add_filter( 'wpml_skip_convert_url_string', '__return_true' );
		$base_url_path = ltrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		remove_filter( 'wpml_skip_convert_url_string', '__return_true' );

		if ( stripos( trailingslashit( $url ), trailingslashit( $base_url_path ) ) === 0 ) {
			$url = substr( $url, strlen( $base_url_path ) );
		}

		$regexUrl = $this->getUrlAsSeenByWpseo( $url, $requestUri );

		foreach ( $redirections as $redirection ) {
			if ( $this->matches( $redirection, $url, $regexUrl ) ) {
				return true;
			}
		}

		return false;
	}

	private function matches( $redirection, string $url, string $regexUrl ): bool {
		if ( isset( $redirection['format'] ) && self::FORMAT_REGEX === $redirection['format'] ) {
			return $this->matchesRegex( $redirection['origin'], $regexUrl );
		}

		return $redirection['origin'] === $url || '/' . $redirection['origin'] === $url;
	}

	private function matchesRegex( string $origin, string $regexUrl ): bool {
		$regex = str_replace( '`', '\\`', $origin );

		return @preg_match( "`{$regex}`", $regexUrl ) === 1;
	}

	private function getUrlAsSeenByWpseo( string $url, string $requestUri ): string {
		$regexUrl = '/' . ltrim( rawurldecode( $url ), '/' );

		if ( '/' !== $regexUrl && substr( $requestUri, -1 ) === '/' ) {
			$regexUrl .= '/';
		}

		return $regexUrl;
	}

	private function get_all_redirections() {
		return get_option( self::OPTION );
	}
}
