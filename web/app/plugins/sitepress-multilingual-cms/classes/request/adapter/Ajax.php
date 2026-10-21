<?php

namespace WPML\Request\Adapter;

use WPML\Request\Policy\Policy;
use WPML\Request\Policy\Registry;

final class Ajax {

	const PREFIX        = 'wp_ajax_';
	const NOPRIV_PREFIX = 'wp_ajax_nopriv_';

	public static function register( $action, Policy $policy, callable $callback, $priority = 10, $acceptedArgs = 1 ) {
		self::declare( $action, $policy );

		add_action( self::PREFIX . $action, $callback, $priority, $acceptedArgs );

		if ( $policy->allowsAnonymous() ) {
			add_action( self::NOPRIV_PREFIX . $action, $callback, $priority, $acceptedArgs );
		}
	}

	public static function declare( $action, Policy $policy ) {
		if ( $policy->isListener() ) {
			throw new \InvalidArgumentException( esc_html( "Action '$action' is WPML-owned; a listener policy only applies to a third-party host action (Ajax::listen())." ) );
		}

		Registry::declare( Registry::AJAX, self::PREFIX . $action, $policy );

		if ( $policy->allowsAnonymous() ) {
			Registry::declare( Registry::AJAX, self::NOPRIV_PREFIX . $action, $policy );
		}

		return $policy;
	}

	public static function listen( $hostAction, callable $callback, $reason, $priority = 10, $acceptedArgs = 1, $alsoNopriv = false ) {
		self::declareListener( $hostAction, $reason, $alsoNopriv );

		add_action( self::PREFIX . $hostAction, $callback, $priority, $acceptedArgs );

		if ( $alsoNopriv ) {
			add_action( self::NOPRIV_PREFIX . $hostAction, $callback, $priority, $acceptedArgs );
		}
	}

	public static function declareListener( $hostAction, $reason, $alsoNopriv = false ) {
		Registry::declareListener( self::PREFIX . $hostAction, $reason );

		if ( $alsoNopriv ) {
			Registry::declareListener( self::NOPRIV_PREFIX . $hostAction, $reason );
		}
	}
}
