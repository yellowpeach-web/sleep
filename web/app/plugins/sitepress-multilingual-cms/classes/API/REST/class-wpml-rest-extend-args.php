<?php

class WPML_REST_Extend_Args implements IWPML_Action {

	const REST_LANGUAGE_ARGUMENT = 'wpml_language';

	private $sitepress;

	private $switches = array();

	private $embed_scope_open = false;

	public function __construct( SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	function add_hooks() {
		add_filter( 'rest_endpoints', array( $this, 'rest_endpoints' ) );
		add_filter( 'rest_request_before_callbacks', array( $this, 'rest_request_before_callbacks' ), 10, 3 );
		add_filter( 'rest_request_after_callbacks', array( $this, 'rest_request_after_callbacks' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'rest_pre_serve_request' ), PHP_INT_MAX, 4 );
		add_filter( 'rest_pre_echo_response', array( $this, 'rest_pre_echo_response' ), PHP_INT_MIN );
	}

	public function rest_endpoints( array $endpoints ) {
		$valid_language_codes = $this->get_active_language_codes();

		foreach ( $endpoints as $route => &$endpoint ) {
			foreach ( $endpoint as $key => &$data ) {
				if ( is_numeric( $key ) ) {
					$data['args'][ self::REST_LANGUAGE_ARGUMENT ] = array(
						'type'        => 'string',
						'description' => "WPML's language code",
						'required'    => false,
						'enum'        => $valid_language_codes,
					);
				}
			}
		}

		return $endpoints;
	}

	public function rest_request_before_callbacks( $response, $rest_server, $request ) {
		$current_language = $this->sitepress->get_current_language();
		$rest_language    = $request->get_param( self::REST_LANGUAGE_ARGUMENT );
		$switch           = $rest_language && $rest_language !== $current_language;

		if ( $switch ) {
			$this->sitepress->switch_lang( $rest_language );
		}
		$this->switches[] = $switch;

		return $response;
	}


	public function rest_request_after_callbacks( $response ) {
		if ( array_pop( $this->switches ) ) {
			$this->sitepress->switch_lang();
		}

		return $response;
	}

	public function rest_pre_serve_request( $served, $result, $request, $rest_server ) {
		if ( $served || ! $request instanceof WP_REST_Request || 'HEAD' === $request->get_method() ) {
			return $served;
		}

		$query_params  = $request->get_query_params();
		$rest_language = $request->get_param( self::REST_LANGUAGE_ARGUMENT );

		if (
			isset( $query_params['_embed'] )
			&& is_string( $rest_language )
			&& in_array( $rest_language, $this->get_active_language_codes(), true )
			&& $rest_language !== $this->sitepress->get_current_language()
		) {
			$this->sitepress->switch_lang( $rest_language );
			$this->embed_scope_open = true;
		}

		return $served;
	}

	public function rest_pre_echo_response( $result ) {
		if ( $this->embed_scope_open ) {
			$this->embed_scope_open = false;
			$this->sitepress->switch_lang();
		}

		return $result;
	}

	private function get_active_language_codes() {
		return array_keys( $this->sitepress->get_active_languages() );
	}
}
