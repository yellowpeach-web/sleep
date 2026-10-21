<?php

namespace WPML\TM\ATE\SiteName;

use WPML\LIB\WP\Hooks as WPHooks;
use function WPML\FP\spreadArgs;

class Hooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	public function add_hooks() {
		WPHooks::onAction( 'update_option_blogname', 10, 2 )
			->then( spreadArgs( [ $this, 'onTitleChanged' ] ) );

		WPHooks::onAction( 'admin_init' )
			->then( [ $this, 'onAdminRequest' ] );
	}

	public function onAdminRequest() {
		if ( wp_doing_ajax() ) {
			return;
		}

		Sender::sendIfStale();
	}

	public function onTitleChanged( $oldValue, $value ) {
		Sender::send();
	}
}
