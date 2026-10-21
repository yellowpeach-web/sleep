<?php

namespace WPML\Request\Adapter;

use WPML\Request\Policy\Policy;
use WPML\Request\Policy\Registry;

final class AdminPost {

	const PREFIX        = 'admin_post_';
	const NOPRIV_PREFIX = 'admin_post_nopriv_';

	public static function register( $action, Policy $policy, callable $callback, $priority = 10, $acceptedArgs = 1 ) {
		if ( $policy->isListener() ) {
			throw new \InvalidArgumentException( esc_html( "admin_post action '$action' is WPML-owned and cannot carry a listener policy." ) );
		}

		Registry::declare( Registry::ADMIN_POST, self::PREFIX . $action, $policy );
		add_action( self::PREFIX . $action, $callback, $priority, $acceptedArgs );

		if ( $policy->allowsAnonymous() ) {
			Registry::declare( Registry::ADMIN_POST, self::NOPRIV_PREFIX . $action, $policy );
			add_action( self::NOPRIV_PREFIX . $action, $callback, $priority, $acceptedArgs );
		}
	}
}
