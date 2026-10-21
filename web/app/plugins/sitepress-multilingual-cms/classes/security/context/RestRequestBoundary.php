<?php

namespace WPML\Security\Context;

use WPML\Core\Security\ExecutionContext\ExecutionContext;
use WPML\Core\Security\ExecutionContext\ExecutionContextHolder;

final class RestRequestBoundary {

	const NAMESPACE_PATTERN = '#^/(wpml|otgs)([/-])#';

	private static $pushed = 0;

	public static function addHooks() {
		add_filter( 'rest_dispatch_request', [ self::class, 'establish' ], 1, 3 );
		add_filter( 'rest_request_after_callbacks', [ self::class, 'release' ], PHP_INT_MAX, 3 );
	}

	public static function establish( $dispatchResult, $request, $route ) {
		if ( self::isWpmlRoute( (string) $route ) ) {
			ExecutionContextHolder::establish(
				ExecutionContext::request( ExecutionContextHolder::currentPrincipalId(), 'rest:' . $route )
			);
			self::$pushed++;
		}

		return $dispatchResult;
	}

	public static function release( $response, $handler = null, $request = null ) {
		if ( self::$pushed > 0 ) {
			ExecutionContextHolder::release();
			self::$pushed--;
		}

		return $response;
	}

	public static function isWpmlRoute( $route ) {
		return (bool) preg_match( self::NAMESPACE_PATTERN, (string) $route );
	}

	public static function reset() {
		self::$pushed = 0;
	}
}
