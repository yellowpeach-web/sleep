<?php

namespace ACFML\Upgrade\Commands;

use ACFML\Strings\Factory;
use ACFML\Strings\HooksFactory;
use WPML\LIB\WP\Hooks;

class RenameFieldGroupPackages implements Command {

	public static function run() {
		Hooks::onAction( 'wp_loaded' )
			->then( function() {
				if ( HooksFactory::isStActivated() ) {
					( new Factory() )->getFieldGroupPackageTitles()->syncAll();
				}
			} );
	}
}
