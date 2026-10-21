<?php

namespace WPML\TM\ATE\REST;

use WPML\TM\ATE\PullDelivery\Spawner;
use WPML\TM\ATE\PullDelivery\State;
use WPML\TM\Jobs\JobLog;
use function WPML\Container\make;

class PullCollect extends \WPML_TM_ATE_Required_Rest_Base {

	const ENDPOINT = '/ate/pull/collect';

	function add_hooks() {
		$this->register_routes();
	}

	function register_routes() {
		parent::register_route(
			self::ENDPOINT,
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'collect' ],
				'args'                => [
					'token' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => [ 'WPML_REST_Arguments_Sanitation', 'string' ],
					],
				],
				'permission_callback' => \WPML\Request\Adapter\Rest::permission(
					\WPML\Request\Policy\Policy::machine(
						[ $this, 'consume_token' ],
						'pull-delivery loopback: single-use spawner token (Spawner::consumeToken)'
					),
					self::REST_NAMESPACE . self::ENDPOINT
				),
			]
		);
	}

	public function consume_token( $request ) {
		$token = is_object( $request ) && method_exists( $request, 'get_param' ) ? $request->get_param( 'token' ) : null;

		if ( ! Spawner::consumeToken( $token ) ) {
			JobLog::maybeInitRequest();

			return new \WP_Error( 403, '', [ 'status' => 403 ] );
		}

		return true;
	}

	public function get_allowed_capabilities( \WP_REST_Request $request ) {
		return [];
	}

	public function collect( \WP_REST_Request $request ) {
		make( Spawner::class )->collect();

		return new \WP_REST_Response( [ 'mode' => State::mode() ], 200 );
	}

	public static function url() {
		return self::get_url( self::ENDPOINT );
	}
}
