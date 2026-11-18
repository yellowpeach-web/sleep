<?php

namespace WPML\TM\ATE\REST;

class PublicReceive extends \WPML_TM_ATE_Required_Rest_Base {

	const CODE_OK        = 200;
	const CODE_FORBIDDEN = 403;

	const ENDPOINT_JOBS_RECEIVE = '/ate/jobs/receive/';

	const REASON_LEVEL_HANDSHAKE = 'level_handshake';

	const TOKEN_PARAM          = 't';
	const TOKEN_MESSAGE_PREFIX = 'ate-receive|';

	const TOKEN_COLUMN = 'ate_receive_token';

	const TOKEN_KIND_PER_JOB = 'per_job';

	const REASON_INVALID_TOKEN = 'wpml_ate_receive_invalid_token';

	function add_hooks() {
		$this->register_routes();
	}

	function register_routes() {
		parent::register_route(
			self::ENDPOINT_JOBS_RECEIVE . '(?P<wpmlJobId>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'receive_ate_job' ),
				'args'                => array(
					'wpmlJobId' => array(
						'required'          => true,
						'type'              => 'int',
						'validate_callback' => array( 'WPML_REST_Arguments_Validation', 'integer' ),
						'sanitize_callback' => array( 'WPML_REST_Arguments_Sanitation', 'integer' ),
					),
				),
				'permission_callback' => \WPML\Request\Adapter\Rest::permission(
					\WPML\Request\Policy\Policy::machine(
						array( $this, 'verify_receive_callback_token' ),
						'ATE delivery callback (answered inert since wpmldev-12216): job-bound token in ?t= - the per-job token stored on the job row, or the pre-upgrade HMAC-SHA256 for a job without one (a missing token reaches the same inert answer)'
					),
					self::REST_NAMESPACE . self::ENDPOINT_JOBS_RECEIVE . '(?P<wpmlJobId>\d+)'
				),
			)
		);
	}

	public function verify_receive_callback_token( \WP_REST_Request $request ) {
		if ( ! $request->has_param( self::TOKEN_PARAM ) ) {
			return true;
		}

		$wpmlJobId = (int) $request->get_param( 'wpmlJobId' );
		$stored    = self::get_stored_receive_token( $wpmlJobId );

		if ( self::matches_receive_token( $wpmlJobId, $request->get_param( self::TOKEN_PARAM ), $stored ) ) {
			return true;
		}

		return self::refuseInvalidToken( $wpmlJobId );
	}

	private static function refuseInvalidToken( $wpmlJobId ) {
		return new \WP_Error(
			self::REASON_INVALID_TOKEN,
			'The delivery credential does not match this job.',
			[
				'status'      => self::CODE_FORBIDDEN,
				'wpml_job_id' => (int) $wpmlJobId,
			]
		);
	}

	public static function get_receive_token( $wpml_job_id ) {
		$stored = self::get_stored_receive_token( $wpml_job_id );

		return '' !== $stored ? $stored : self::get_salt_keyed_token( $wpml_job_id );
	}

	private static function get_salt_keyed_token( $wpml_job_id ) {
		$salt = wp_salt( 'auth' );
		if ( ! is_string( $salt ) || strlen( $salt ) < 32 ) {
			return '';
		}

		return hash_hmac( 'sha256', self::TOKEN_MESSAGE_PREFIX . (int) $wpml_job_id, $salt );
	}

	public static function get_stored_receive_token( $wpml_job_id ) {
		return (string) self::read_stored_receive_token( $wpml_job_id );
	}

	private static function read_stored_receive_token( $wpml_job_id ) {
		global $wpdb;

		if ( (int) $wpml_job_id < 1 ) {
			return '';
		}

		$suppressed = $wpdb->suppress_errors( true );

		$token = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ate_receive_token FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d",
				(int) $wpml_job_id
			)
		);

		$failed = '' !== (string) ( $wpdb->last_error ?? '' );

		$wpdb->suppress_errors( (bool) $suppressed );

		if ( $failed ) {
			return null;
		}

		return is_string( $token ) ? $token : '';
	}

	public static function is_valid_receive_token( $wpml_job_id, $token ) {
		return self::matches_receive_token( $wpml_job_id, $token, self::get_stored_receive_token( $wpml_job_id ) );
	}

	private static function matches_receive_token( $wpml_job_id, $token, $stored ) {
		$expected = '' !== $stored ? $stored : self::get_salt_keyed_token( $wpml_job_id );

		return '' !== $expected
			&& is_string( $token )
			&& hash_equals( $expected, $token );
	}

	public function get_allowed_capabilities( \WP_REST_Request $request ) {
		return [];
	}

	public function receive_ate_job( \WP_REST_Request $request ) {
		return new \WP_REST_Response( [ 'code' => self::REASON_LEVEL_HANDSHAKE ], self::CODE_OK );
	}

	public static function get_receive_ate_job_url( $wpml_job_id ) {
		$url   = self::get_language_neutral_url( self::ENDPOINT_JOBS_RECEIVE . $wpml_job_id );
		$token = self::mint_receive_token( (int) $wpml_job_id );
		$token = '' !== $token ? $token : self::get_salt_keyed_token( (int) $wpml_job_id );

		return $token ? add_query_arg( self::TOKEN_PARAM, $token, $url ) : $url;
	}

	private static function get_language_neutral_url( string $endpoint ): string {
		global $wpml_url_converter;

		if ( ! $wpml_url_converter instanceof \WPML_URL_Converter ) {
			return self::get_url( $endpoint );
		}

		$strategy = $wpml_url_converter->get_strategy();
		$strategy->remove_hooks();
		try {
			return self::get_url( $endpoint );
		} finally {
			$strategy->add_hooks();
		}
	}

	private static function mint_receive_token( $wpml_job_id ) {
		global $wpdb;

		if ( $wpml_job_id < 1 ) {
			return '';
		}

		$stored = self::read_stored_receive_token( $wpml_job_id );
		if ( null === $stored ) {
			return '';
		}

		if ( '' !== $stored ) {
			return $stored;
		}

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_translate_job SET ate_receive_token = %s WHERE job_id = %d AND ( ate_receive_token IS NULL OR ate_receive_token = '' )",
				bin2hex( random_bytes( 32 ) ),
				$wpml_job_id
			)
		);

		return (string) self::read_stored_receive_token( $wpml_job_id );
	}

	public static function receive_token_kind( $wpml_job_id ) {
		return '' !== self::get_stored_receive_token( (int) $wpml_job_id ) ? self::TOKEN_KIND_PER_JOB : null;
	}
}
