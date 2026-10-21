<?php

class WPML_Language_Domain_Validation {
	const VALIDATE_DOMAIN_KEY = '____icl_validate_domain';

	const VALIDATION_HEADER = 'X-WPML-Domain-Validation';

	const VALID = 'valid';

	const BLOCKED = 'blocked';

	const WRONG_CONTENT = 'wrong_content';

	const UNREACHABLE = 'unreachable';

	private $wp_api;
	private $http;
	private $url;
	private $validation_url;


	public function __construct( WPML_WP_API $wp_api, WP_Http $http) {
		$this->wp_api         = $wp_api;
		$this->http           = $http;
	}

	public function is_valid( $url ) {
		return self::VALID === $this->validate( $url );
	}

	public function validate( $url ) {
		$this->url            = $url;
		$this->validation_url = $this->get_validation_url();
		if ( ! $this->has_scheme_and_host() ) {
			return self::UNREACHABLE;
		}

		if ( is_multisite() && defined( 'SUBDOMAIN_INSTALL' ) && SUBDOMAIN_INSTALL ) {
			return self::VALID;
		}

		$response = $this->get_validation_response();

		if ( $this->is_valid_response( $response ) ) {
			return $this->body_matches( $response ) || $this->header_matches( $response ) ? self::VALID : self::WRONG_CONTENT;
		}

		return $this->domain_loads() ? self::BLOCKED : self::UNREACHABLE;
	}

	public static function label( $result ) {
		if ( self::VALID === $result ) {
			/* translators: Answer returned when what was entered is in order. */
			return __( 'Valid', 'sitepress' );
		}

		if ( self::BLOCKED === $result ) {
			/* translators: Shown next to a language domain when the domain loads, but a firewall or a CDN (content delivery network) stopped WPML's check that it shows this site. */
			return __( 'Blocked by a firewall or CDN', 'sitepress' );
		}

		/* translators: Answer returned when what was entered is not in order. */
		return __( 'Not valid', 'sitepress' );
	}

	private function has_scheme_and_host() {
		$url_parts = wpml_parse_url( $this->url );
		return array_key_exists( 'scheme', $url_parts ) && array_key_exists( 'host', $url_parts );
	}

	private function get_validation_url() {
		return add_query_arg( array( self::VALIDATE_DOMAIN_KEY => 1 ), trailingslashit( $this->url ) );
	}

	private function get_accepted_responses( $url ) {
		$accepted_responses = array(
			'<!--' . untrailingslashit( $this->wp_api->get_home_url() ) . '-->',
			'<!--' . untrailingslashit( $this->wp_api->get_site_url() ) . '-->',
		);
		if ( defined( 'SUNRISE' ) && SUNRISE === 'on' ) {
			$accepted_responses[] = '<!--' . str_replace( '?' . self::VALIDATE_DOMAIN_KEY . '=1', '', $url ) . '-->';
			return $accepted_responses;
		}
		return $accepted_responses;
	}

	private function body_matches( $response ) {
		return in_array( $response['body'], $this->get_accepted_responses( $this->validation_url ), true );
	}

	private function header_matches( $response ) {
		$name = strtolower( self::VALIDATION_HEADER );

		if ( ! isset( $response['headers'][ $name ] ) ) {
			return false;
		}

		$expected = array(
			untrailingslashit( (string) $this->wp_api->get_home_url() ),
			untrailingslashit( (string) $this->wp_api->get_site_url() ),
		);

		foreach ( (array) $response['headers'][ $name ] as $value ) {
			$value = trim( (string) $value );
			if ( '' !== $value && in_array( $value, $expected, true ) ) {
				return true;
			}
		}

		return false;
	}

	private function get_validation_response() {
		return $this->http->request( $this->validation_url, 'timeout=15' );
	}

	private function domain_loads() {
		$response = $this->http->request( trailingslashit( $this->url ), 'timeout=15' );

		if ( is_wp_error( $response ) || ! isset( $response['response']['code'] ) ) {
			return false;
		}

		$code = (int) $response['response']['code'];

		return $code >= 200 && $code < 300;
	}

	private function is_valid_response( $response ) {
		return ! is_wp_error( $response ) && '200' === (string) $response['response']['code'];
	}
}
