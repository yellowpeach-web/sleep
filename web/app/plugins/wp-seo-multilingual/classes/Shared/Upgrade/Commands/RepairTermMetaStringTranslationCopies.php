<?php

namespace WPML\WPSEO\Shared\Upgrade\Commands;

use function WPML\Container\make;

class RepairTermMetaStringTranslationCopies implements Command {

	public static function run() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			make( \WPML\WPSEO\Shared\Upgrade\TermMetaRepair::class )->runStringTranslationCopies();
		}
	}
}
