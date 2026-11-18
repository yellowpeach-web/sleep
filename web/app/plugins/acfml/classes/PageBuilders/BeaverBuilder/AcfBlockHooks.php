<?php

namespace ACFML\PageBuilders\BeaverBuilder;

class AcfBlockHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const MODULE_TYPE = 'acf-block';

	public function add_hooks() {
		add_filter( 'wpml_beaver_builder_modules_to_translate', [ $this, 'addAcfBlockModule' ] );
	}

	public function addAcfBlockModule( $modules ) {
		$modules[ self::MODULE_TYPE ] = [
			'conditions'        => [ 'type' => self::MODULE_TYPE ],
			'fields'            => [],
			'integration-class' => AcfBlockModule::class,
		];

		return $modules;
	}
}
