<?php

class WPML_TM_ATE_Authentication {
	const AMS_DATA_KEY          = 'WPML_TM_AMS';
	const AMS_STATUS_NON_ACTIVE = 'non-active';
	const AMS_STATUS_ENABLED    = 'enabled';
	const AMS_STATUS_ACTIVE     = 'active';

	private $site_id = null;

	private $site_id_manager;

	public function __construct( ?WPML_Site_ID $site_id_manager = null ) {
		$this->site_id_manager = $site_id_manager ?: new WPML_Site_ID();
	}

	public function get_signed_url_with_parameters( $verb, $url, $params = null ) {
		if ( ! $this->has_keys() ) {
			return new WP_Error( 'auth_error', 'Unable to authenticate' );
		}

		$url = $this->add_required_arguments_to_url( $verb, $url, $params );

		return $this->signUrl( $verb, $url, $params );
	}

	public function get_signed_url_without_parameters( $verb, $url, $params = null ) {
		if ( ! $this->has_keys() ) {
			return new WP_Error( 'auth_error', 'Unable to authenticate' );
		}

		$url = $this->add_required_arguments_to_url( $verb, $url, $params );

		return $this->add_signature_to_url(
			$url,
			$this->get_signature_for_base_endpoint( $verb, $url, $params )
		);
	}

	public function signUrl( $verb, $url, $params = null, $secret = null ) {
		return $this->add_signature_to_url(
			$url,
			$this->get_signature_for_full_url( $verb, $url, $params, $secret )
		);
	}

	private function add_signature_to_url( $url, $signature ) {
		$url_parts = wp_parse_url( $url );

		$query              = $this->get_url_query( $url );
		$query['signature'] = $signature;

		$url_parts['query'] = $this->build_query( $query );

		return http_build_url( $url_parts );
	}

	private function get_signature_for_full_url( $verb, $url, ?array $params = null, $secret = null ) {
		$secret = $secret ?: $this->get_secret();

		if ( ! $secret ) {
			return null;
		}

		$verb      = strtolower( $verb );
		$url_parts = wp_parse_url( $url );

		$query_to_sign = $this->get_url_query( $url );

		$body_hash = $this->get_body_hash( $verb, $params );
		if ( $body_hash ) {
			$query_to_sign['body'] = $body_hash;
		}

		$url_parts_to_sign          = $url_parts;
		$url_parts_to_sign['query'] = $this->build_query( $query_to_sign );

		$url_to_sign = http_build_url( $url_parts_to_sign );

		return $this->sign_string( $verb . $url_to_sign, $secret );
	}

	private function get_signature_for_base_endpoint( $verb, $url, ?array $params = null ) {
		$secret = $this->get_secret();

		if ( ! $secret ) {
			return null;
		}

		$verb = strtolower( $verb );

		$string_to_sign = $verb . $this->get_base_endpoint_url_to_sign( $url );

		$body_hash = $this->get_body_hash( $verb, $params );
		if ( $body_hash ) {
			$string_to_sign .= '&body=' . $body_hash;
		}

		return $this->sign_string( $string_to_sign, $secret );
	}

	private function get_body_hash( $verb, ?array $params = null ) {
		if ( ! $params || 'get' === $verb ) {
			return null;
		}

		return md5( (string) wp_json_encode( $params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	private function sign_string( $string_to_sign, $secret ) {
		return base64_encode( hash_hmac( 'sha1', $string_to_sign, $secret, true ) );
	}

	private function get_base_endpoint_url_to_sign( $url ) {
		$url_parts = wp_parse_url( $url );

		if ( ! is_array( $url_parts ) ) {
			return $url;
		}

		$host = array_key_exists( 'host', $url_parts ) ? $url_parts['host'] : '';
		$port = array_key_exists( 'port', $url_parts ) ? ':' . $url_parts['port'] : '';
		$path = array_key_exists( 'path', $url_parts ) ? $url_parts['path'] : '';

		return $host . $port . $path;
	}

	public function has_keys() {
		return $this->get_secret() && $this->get_shared();
	}

	private function get_secret() {
		return $this->get_ams_data_property( 'secret' );
	}

	private function get_shared() {
		return $this->get_ams_data_property( 'shared' );
	}

	private function get_ams_data_property( $field ) {
		$data = $this->get_ams_data();
		if ( array_key_exists( $field, $data ) ) {
			return $data[ $field ];
		}

		return null;
	}

	private function get_ams_data() {
		$data = get_option( self::AMS_DATA_KEY, [] );

		return is_array( $data ) ? $data : [];
	}

	private function add_required_arguments_to_url( $verb, $url, ?array $params = null ) {
		$verb = strtolower( $verb );

		$url_parts = wp_parse_url( $url );

		$query = $this->get_url_query( $url );
		if ( $params && 'get' === $verb ) {
			foreach ( $params as $key => $value ) {
				$query[ $key ] = $value;
			}
		}

		$query['wpml_core_version'] = ICL_SITEPRESS_VERSION;
		$query['wpml_tm_version']   = defined( 'WPML_TM_VERSION' ) ? WPML_TM_VERSION : '1.0';
		$query['shared_key']        = $this->get_shared();
		$query['token']             = uuid_v5( wp_generate_uuid4(), $url );
		$query['website_uuid']      = $this->get_site_id();
		$query['ui_language_code']  = apply_filters(
			'wpml_get_user_admin_language',
			wpml_get_default_language(),
			get_current_user_id()
		);
		if ( isset( $params['site_key'] ) ) {
			$query['site_key'] = $params['site_key'];
		} elseif ( function_exists( 'OTGS_Installer' ) ) {
			$query['site_key'] = OTGS_Installer()->get_site_key( 'wpml' );
		}

		$url_parts['query'] = wpml_http_build_query( $query );

		return http_build_url( $url_parts );
	}

	private function get_url_query( $url ) {
		$url_parts = wp_parse_url( $url );
		$query     = array();
		if ( is_array( $url_parts ) && array_key_exists( 'query', $url_parts ) ) {
			parse_str( $url_parts['query'], $query );
		}

		return $query;
	}

	protected function build_query( $query ) {
		if ( PHP_VERSION_ID >= 50400 ) {
			$final_query = http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		} else {
			$final_query = str_replace(
				array( '+', '%7E' ),
				array( '%20', '~' ),
				http_build_query( $query )
			);
		}

		return $final_query;
	}

	public function override_site_id( $site_id ) {
		$this->site_id = $site_id;
	}

	public function get_site_id() {
		return $this->site_id ? $this->site_id : wpml_get_site_id( WPML_TM_ATE::SITE_ID_SCOPE );
	}

	public function reset() {
		delete_option( self::AMS_DATA_KEY );
		$this->site_id_manager->reset( 'ate' );
		$this->site_id = null;
	}
}
