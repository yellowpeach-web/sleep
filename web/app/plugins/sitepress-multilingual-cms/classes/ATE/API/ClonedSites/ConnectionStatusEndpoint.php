<?php

namespace WPML\TM\ATE\ClonedSites;

use WPML\Request\Adapter\Rest;
use WPML\Request\Policy\Authenticity;
use WPML\Request\Policy\Policy;

class ConnectionStatusEndpoint implements \IWPML_REST_Action, \IWPML_DIC_Action {

	const REST_NAMESPACE = 'wpml/v1';
	const ROUTE_STATUS   = '/ate/connection';
	const ROUTE_PROBE    = '/ate/connection/probe';
	const ROUTE_HIDE     = '/ate/connection/hide-support';

	private $probe;

	private $auth;

	public function __construct( ConnectionProbe $probe, \WPML_TM_ATE_Authentication $auth ) {
		$this->probe = $probe;
		$this->auth  = $auth;
	}

	public function add_hooks() {
		add_action( 'rest_api_init', [ $this, 'registerRoutes' ] );
	}

	public function registerRoutes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::ROUTE_STATUS,
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'status' ],
				'permission_callback' => Rest::permission( $this->policy(), self::REST_NAMESPACE . self::ROUTE_STATUS ),
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			self::ROUTE_PROBE,
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'probe' ],
				'permission_callback' => Rest::permission( $this->policy(), self::REST_NAMESPACE . self::ROUTE_PROBE ),
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			self::ROUTE_HIDE,
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'hideSupport' ],
				'permission_callback' => Rest::permission( $this->policy(), self::REST_NAMESPACE . self::ROUTE_HIDE ),
				'args'                => [
					'code' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	private function policy() {
		return Policy::capability(
			[ 'manage_options', 'manage_translations', 'translate', 'wpml_manage_languages' ],
			Authenticity::restTransport()
		);
	}

	public function status() {
		$probed = false;

		if (
			ReconnectState::get()
			&& ReconnectState::isNoAnswer()
			&& ReconnectState::isReconnecting()
			&& ReconnectState::isDue()
			&& ! ReconnectState::openedByCreate()
		) {
			$this->probe->run();
			$probed = true;
		}

		return $this->answer( $probed );
	}

	public function probe() {
		$this->probe->run();

		return $this->answer( true );
	}

	public function hideSupport( $request ) {
		$code = $request instanceof \WP_REST_Request ? (string) $request->get_param( 'code' ) : '';

		if ( '' !== $code ) {
			ReconnectState::dismissEscalation( $code );
		}

		return $this->answer( false );
	}

	private function answer( $probed ) {
		$payload           = ReconnectNotice::bootstrap( (string) $this->auth->get_site_id() );
		$payload['probed'] = (bool) $probed;

		return rest_ensure_response( $payload );
	}
}
