<?php

namespace WPML\WPSEO\Shared\Upgrade\Commands;

use function WPML\Container\make;

class InvalidateTermIndexables implements Command {

	public static function run() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			make( \WPML\WPSEO\YoastSEO\Indexable\Hooks::class )->invalidateTermIndexables();
		}
	}
}
