<?php

namespace WPML\TM\ATE\REST;

use WP_REST_Request;
use WPML\TM\ATE\PullDelivery\Capability;
use WPML\TM\ATE\PullDelivery\ForceFlag;
use WPML\TM\ATE\PullDelivery\PingHandler;
use WPML\TM\REST\Base;
use function WPML\Container\make;

class PullPing extends Base {

	const ENDPOINT = '/ate/pull/ping';

	public function get_routes() {
		return [
			[
				'route' => self::ENDPOINT,
				'args'  => [
					'methods'  => 'POST',
					'callback' => [ $this, 'ping' ],
					'args'     => [
						'tabId' => self::getStringType(),
						'force' => [
							'type'    => 'boolean',
							'default' => false,
						],
						'wake'  => [
							'type'    => 'boolean',
							'default' => false,
						],
					],
				],
			],
		];
	}

	public function get_allowed_capabilities( WP_REST_Request $request ) {
		return Capability::PING;
	}

	public function ping( WP_REST_Request $request ) {
		return make( PingHandler::class )->handle(
			(string) $request->get_param( 'tabId' ),
			ForceFlag::wasRequested( $request->get_param( 'force' ) ),
			ForceFlag::wasRequested( $request->get_param( 'wake' ) )
		);
	}

	public static function url() {
		return get_rest_url( null, '/wpml/tm/v1' . self::ENDPOINT );
	}
}
