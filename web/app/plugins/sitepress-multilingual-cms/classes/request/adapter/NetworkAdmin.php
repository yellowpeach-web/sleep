<?php

namespace WPML\Request\Adapter;

use WPML\Core\Security\ExecutionContext\ExecutionContext;
use WPML\Core\Security\ExecutionContext\ExecutionContextHolder;
use WPML\Request\Policy\Policy;
use WPML\Request\Policy\Registry;

final class NetworkAdmin {

	const HOOK = 'wpmuadminedit';

	private static $handlers = [];

	private static $hooked = false;

	public static function register( $action, Policy $policy, callable $handler ) {
		if ( $policy->isListener() ) {
			throw new \InvalidArgumentException( esc_html( "Network action '$action' is WPML-owned and cannot carry a listener policy." ) );
		}

		Registry::declare( Registry::NETWORK, (string) $action, $policy );
		self::$handlers[ (string) $action ] = $handler;

		if ( ! self::$hooked ) {
			add_action( self::HOOK, [ self::class, 'dispatch' ] );
			self::$hooked = true;
		}
	}

	public static function dispatch() {
		$action = self::requestedAction();
		if ( '' === $action || ! isset( self::$handlers[ $action ] ) ) {
			return;
		}

		$policy = Registry::policyFor( Registry::NETWORK, $action );
		if ( ! $policy || ! $policy->permits() ) {
			wp_die( esc_html( Gate::DENIED_MESSAGE ), '', [ 'response' => 403 ] );

			return;
		}

		ExecutionContextHolder::within(
			ExecutionContext::request( ExecutionContextHolder::currentPrincipalId(), Registry::NETWORK . ':' . $action ),
			function () use ( $action ) {
				call_user_func( self::$handlers[ $action ] );
			}
		);
	}

	public static function requestedAction() {
		foreach ( [ $_POST, $_GET ] as $source ) {
			if ( isset( $source['action'] ) && is_string( $source['action'] ) && '' !== $source['action'] ) {
				$action = filter_var( $source['action'], FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );

				return is_string( $action ) ? $action : '';
			}
		}

		return '';
	}

	public static function reset() {
		self::$handlers = [];
		self::$hooked   = false;
	}
}
