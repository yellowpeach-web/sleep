<?php

namespace WPML\Ajax\Authorization;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;

class Authorization {

	const ERROR = 'Insufficient permissions';

	public static function isAuthorized( $endpoint, callable $resolve, Collection $data ) {
		if ( ! is_string( $endpoint ) || ! class_exists( $endpoint ) ) {
			return false;
		}

		if ( is_a( $endpoint, IHandler::class, true ) ) {
			if ( is_a( $endpoint, PublicHandler::class, true ) ) {
				return true;
			}

			if ( self::declaresAuthorize( $endpoint ) ) {
				$handler = $resolve();

				return $handler instanceof IHandler && self::approves( $handler, $data );
			}
		}

		$policies = EndpointPolicies::map();
		if ( array_key_exists( $endpoint, $policies ) ) {
			return self::currentUserCanAny( $policies[ $endpoint ] );
		}

		return false;
	}

	private static function declaresAuthorize( $class ) {
		if ( is_a( $class, Authorized::class, true ) ) {
			return true;
		}

		try {
			$method = new \ReflectionMethod( $class, 'authorize' );

			return $method->isPublic() && ! $method->isStatic() && ! $method->isAbstract();
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	private static function approves( $handler, Collection $data ) {
		try {
			return true === $handler->authorize( $data );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	private static function currentUserCanAny( $capabilities ) {
		if ( current_user_can( EndpointPolicies::MANAGE_OPTIONS ) ) {
			return true;
		}

		foreach ( (array) $capabilities as $capability ) {
			if ( is_string( $capability ) && current_user_can( $capability ) ) {
				return true;
			}
		}

		return false;
	}
}
