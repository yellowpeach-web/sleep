<?php

namespace ACFML\Upgrade\Commands;

use ACFML\Options\ValueRegistration;
use ACFML\Strings\BackFill;
use ACFML\Strings\Factory;
use ACFML\Strings\Translator;
use WPML\LIB\WP\Hooks;
use function WPML\Container\make;

class RegisterMissingStrings implements Command {

	public static function run() {
		Hooks::onAction( 'wp_loaded' )
			->then(
				function () {
					if ( BackFill::isDone() || ! BackFill::canRun() ) {
						return;
					}

					$factory = new Factory();

					$backFill = new BackFill(
						new Translator( $factory ),
						make( ValueRegistration::class )
					);
					$backFill->runOnce();
				}
			);
	}
}
