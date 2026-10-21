<?php

namespace WPML\Request\Adapter;

use WPML\Request\Policy\Policy;
use WPML\Request\Policy\Registry;

final class XmlRpc {

	private $policy;

	private $handler;

	private function __construct( Policy $policy, callable $handler ) {
		$this->policy  = $policy;
		$this->handler = $handler;
	}

	public static function method( array $methods, $name, Policy $policy, callable $handler ) {
		if ( $policy->isListener() ) {
			throw new \InvalidArgumentException( esc_html( "XML-RPC method '$name' is WPML-owned and cannot carry a listener policy." ) );
		}

		Registry::declare( Registry::XMLRPC, (string) $name, $policy );

		$binding          = new self( $policy, $handler );
		$methods[ $name ] = [ $binding, 'dispatch' ];

		return $methods;
	}

	public function dispatch( $args = [] ) {
		$verdict = $this->policy->evaluate( $args );

		if ( true !== $verdict ) {
			return self::error( $verdict );
		}

		return call_user_func( $this->handler, $args );
	}

	private static function error( $verdict ) {
		if ( is_object( $verdict ) && ( is_a( $verdict, 'IXR_Error' ) || is_a( $verdict, 'WP_Error' ) ) ) {
			return $verdict;
		}

		if ( class_exists( 'IXR_Error' ) ) {
			return new \IXR_Error( 403, Gate::DENIED_MESSAGE );
		}

		return (object) [ 'code' => 403, 'message' => Gate::DENIED_MESSAGE ];
	}
}
