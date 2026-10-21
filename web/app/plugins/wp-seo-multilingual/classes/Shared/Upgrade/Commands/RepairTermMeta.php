<?php

namespace WPML\WPSEO\Shared\Upgrade\Commands;

use function WPML\Container\make;

class RepairTermMeta implements Command {

	public static function run() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			make( \WPML\WPSEO\Shared\Upgrade\TermMetaRepair::class )->run();
		}
	}
}
