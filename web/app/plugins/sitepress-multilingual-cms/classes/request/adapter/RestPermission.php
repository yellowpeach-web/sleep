<?php

namespace WPML\Request\Adapter;

use WPML\Request\Policy\Policy;
use WPML\Request\Policy\Registry;

final class RestPermission {

	private $policy;

	private $route;

	public function __construct( Policy $policy, $route ) {
		$this->policy = $policy;
		$this->route  = $route;

		Registry::declare( Registry::REST, $route, $policy );
	}

	public function __invoke( $request = null ) {
		$verdict = $this->policy->evaluate( $request );

		if ( true === $verdict ) {
			return true;
		}
		if ( is_object( $verdict ) && is_a( $verdict, 'WP_Error' ) ) {
			return $verdict;
		}

		return new \WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to do that.', 'sitepress' ),
			[ 'status' => function_exists( 'rest_authorization_required_code' ) ? rest_authorization_required_code() : 403 ]
		);
	}

	public function policy() {
		return $this->policy;
	}

	public function route() {
		return $this->route;
	}
}
