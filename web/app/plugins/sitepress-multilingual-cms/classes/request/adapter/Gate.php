<?php

namespace WPML\Request\Adapter;

use WPML\Core\Security\ExecutionContext\ExecutionContext;
use WPML\Core\Security\ExecutionContext\ExecutionContextHolder;
use WPML\Request\Policy\Policy;
use WPML\Request\Policy\Registry;
use function WPML\PHP\Logger\error as logError;

final class Gate {

	const PRIORITY = PHP_INT_MIN;

	const DENIED_MESSAGE = 'Insufficient permissions';

	const UNKNOWN_ACTION_BODY = '0';

	private static $denier = null;

	private static $unknownActionResponder = null;

	public static function addHooks() {
		add_action( 'admin_init', [ self::class, 'arm' ], PHP_INT_MAX );
	}

	public static function arm() {
		$action = self::requestedAction();
		if ( '' === $action ) {
			return;
		}

		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			add_action( Ajax::PREFIX . $action, [ self::class, 'enforceAjax' ], self::PRIORITY );
			add_action( Ajax::NOPRIV_PREFIX . $action, [ self::class, 'enforceAjax' ], self::PRIORITY );

			return;
		}

		if ( self::isAdminPostRequest() ) {
			add_action( AdminPost::PREFIX . $action, [ self::class, 'enforceAdminPost' ], self::PRIORITY );
			add_action( AdminPost::NOPRIV_PREFIX . $action, [ self::class, 'enforceAdminPost' ], self::PRIORITY );
		}
	}

	public static function enforceAjax() {
		self::enforce( (string) current_filter(), Registry::AJAX );
	}

	public static function enforceAdminPost() {
		self::enforce( (string) current_filter(), Registry::ADMIN_POST );
	}

	public static function enforce( $hook, $transport ) {
		$policy = Registry::policyFor( $transport, $hook );

		if ( $policy ) {
			$verdict = $policy->evaluate();
			if ( true === $verdict ) {
				self::establishContext( $transport, $hook );

				return true;
			}

			self::deny( $transport, $hook, $policy, $verdict );

			return false;
		}

		self::detachUngoverned( $hook );

		if ( Registry::AJAX === $transport && ! self::hasHandlerBesidesGate( $hook ) ) {
			self::denyUnknownAction();

			return false;
		}

		return true;
	}

	public static function detachUngoverned( $hook ) {
		if ( Registry::listenerPolicyFor( $hook ) ) {
			return [];
		}

		$detached = [];

		foreach ( self::callbacksOn( $hook ) as $priority => $callbacks ) {
			foreach ( $callbacks as $entry ) {
				$callback = isset( $entry['function'] ) ? $entry['function'] : null;
				if ( null === $callback || self::isGate( $callback ) || ! Registry::isOwnedCallback( $callback ) ) {
					continue;
				}

				remove_action( $hook, $callback, $priority );

				$description = Registry::describeCallback( $callback );
				$detached[]  = $description;
				Registry::recordDetached( $hook, $description );
				logError( sprintf( 'WPML request policy: detached ungoverned handler %s from %s (no policy declared, wpmldev-7976).', $description, $hook ) );
			}
		}

		return $detached;
	}

	public static function setDenier( ?callable $denier = null ) {
		self::$denier = $denier;
	}

	public static function setUnknownActionResponder( ?callable $responder = null ) {
		self::$unknownActionResponder = $responder;
	}

	private static function hasHandlerBesidesGate( $hook ) {
		foreach ( self::callbacksOn( $hook ) as $callbacks ) {
			foreach ( $callbacks as $entry ) {
				$callback = isset( $entry['function'] ) ? $entry['function'] : null;
				if ( null !== $callback && ! self::isGate( $callback ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function denyUnknownAction() {
		if ( self::$unknownActionResponder ) {
			call_user_func( self::$unknownActionResponder );

			return;
		}

		wp_die( esc_html( self::UNKNOWN_ACTION_BODY ), '', [ 'response' => 400 ] );
	}

	private static function deny( $transport, $hook, Policy $policy, $verdict ) {
		if ( self::$denier ) {
			call_user_func( self::$denier, $transport, $hook, $policy, $verdict );

			return;
		}

		if ( Registry::AJAX === $transport ) {
			wp_send_json_error( [ 'code' => Policy::DENIED, 'message' => self::DENIED_MESSAGE ], 403 );

			return;
		}

		wp_die( esc_html( self::DENIED_MESSAGE ), '', [ 'response' => 403 ] );
	}

	private static function establishContext( $transport, $hook ) {
		ExecutionContextHolder::establish(
			ExecutionContext::request( ExecutionContextHolder::currentPrincipalId(), $transport . ':' . $hook )
		);
	}

	private static function callbacksOn( $hook ) {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $hook ] ) ) {
			return [];
		}
		$entry = $wp_filter[ $hook ];
		if ( is_object( $entry ) && isset( $entry->callbacks ) && is_array( $entry->callbacks ) ) {
			return $entry->callbacks;
		}

		return is_array( $entry ) ? $entry : [];
	}

	private static function isGate( $callback ) {
		return is_array( $callback )
			&& isset( $callback[0], $callback[1] )
			&& self::class === $callback[0]
			&& in_array( $callback[1], [ 'enforceAjax', 'enforceAdminPost' ], true );
	}

	private static function requestedAction() {
		if ( ! isset( $_REQUEST['action'] ) || ! is_scalar( $_REQUEST['action'] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( (string) $_REQUEST['action'] ) );
	}

	private static function isAdminPostRequest() {
		if ( isset( $GLOBALS['pagenow'] ) && 'admin-post.php' === $GLOBALS['pagenow'] ) {
			return true;
		}
		$script = isset( $_SERVER['SCRIPT_FILENAME'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['SCRIPT_FILENAME'] ) ) : '';

		return 'admin-post.php' === basename( $script );
	}
}
