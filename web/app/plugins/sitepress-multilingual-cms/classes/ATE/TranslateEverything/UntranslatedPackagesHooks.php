<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\LIB\WP\Hooks;
use function WPML\Container\make;
use function WPML\FP\spreadArgs;

class UntranslatedPackagesHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_REST_Action, \IWPML_AJAX_Action {

	public function add_hooks() {
		Hooks::onAction( 'wpml_st_package_created' )
			->then( spreadArgs( [ $this, 'onPackageCreated' ] ) );
	}

	public function onPackageCreated( $package ) {
		$kind = $package instanceof \WPML_Package ? (string) $package->kind_slug : '';

		if ( '' === $kind ) {
			return;
		}

		make( UntranslatedPackages::class )->markKindAsUncompleted( $kind );
	}
}
