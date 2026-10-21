<?php

namespace WPML\PB\Request;

final class Ajax {

	public static function register( $action, array $spec, callable $callback, $priority = 10, $acceptedArgs = 1 ) {
		if ( self::coreAvailable() ) {
			\WPML\Request\Adapter\Ajax::register( $action, self::policy( $spec, $action ), $callback, $priority, $acceptedArgs );

			return;
		}

		add_action( 'wp_ajax_' . $action, $callback, $priority, $acceptedArgs );
		if ( isset( $spec['public'] ) || isset( $spec['machine'] ) ) {
			add_action( 'wp_ajax_nopriv_' . $action, $callback, $priority, $acceptedArgs );
		}
	}

	public static function listen( $hostAction, callable $callback, $reason, $priority = 10, $acceptedArgs = 1, $alsoNopriv = false ) {
		if ( self::coreAvailable() ) {
			\WPML\Request\Adapter\Ajax::listen( $hostAction, $callback, $reason, $priority, $acceptedArgs, $alsoNopriv );

			return;
		}

		add_action( 'wp_ajax_' . $hostAction, $callback, $priority, $acceptedArgs );
		if ( $alsoNopriv ) {
			add_action( 'wp_ajax_nopriv_' . $hostAction, $callback, $priority, $acceptedArgs );
		}
	}

	public static function coreAvailable() {
		static $available = null;

		if ( null === $available ) {
			$available = class_exists( '\WPML\Request\Adapter\Ajax' ) && class_exists( '\WPML\Request\Policy\Policy' );
			if ( $available ) {
				\WPML\Request\Policy\Registry::ownRoot( dirname( dirname( __DIR__ ) ) );
			}
		}

		return $available;
	}

	public static function policy( array $spec, $action ) {
		if ( isset( $spec['nonce'] ) && is_array( $spec['nonce'] ) ) {
			$authenticity = \WPML\Request\Policy\Authenticity::actionNonce( $spec['nonce'][0], isset( $spec['nonce'][1] ) ? $spec['nonce'][1] : 'nonce' );
		} elseif ( isset( $spec['machine'] ) ) {
			$authenticity = null;
		} else {
			$authenticity = \WPML\Request\Policy\Authenticity::none( isset( $spec['no_nonce'] ) ? (string) $spec['no_nonce'] : '' );
		}

		if ( isset( $spec['capability'] ) ) {
			return \WPML\Request\Policy\Policy::capability( $spec['capability'], $authenticity );
		}
		if ( isset( $spec['authorize'] ) ) {
			return \WPML\Request\Policy\Policy::authorize( $spec['authorize'], $authenticity, isset( $spec['description'] ) ? $spec['description'] : '' );
		}
		if ( isset( $spec['authenticated'] ) ) {
			return \WPML\Request\Policy\Policy::authenticated( $authenticity, (string) $spec['authenticated'] );
		}
		if ( isset( $spec['public'] ) ) {
			return \WPML\Request\Policy\Policy::publicAccess( (string) $spec['public'], $authenticity );
		}
		if ( isset( $spec['machine'] ) ) {
			return \WPML\Request\Policy\Policy::machine( $spec['machine'], isset( $spec['description'] ) ? $spec['description'] : '' );
		}

		return \WPML\Request\Policy\Policy::authorize(
			function () {
				return false;
			},
			\WPML\Request\Policy\Authenticity::none( 'malformed policy spec' ),
			'fail-closed: malformed policy spec for ' . $action
		);
	}
}
