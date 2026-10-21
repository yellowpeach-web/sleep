<?php

namespace WPML\Compatibility\Divi\V5;

use function WPML\Container\make;

class GlobalLayoutStringsFactory implements \IWPML_Backend_Action_Loader, \IWPML_Frontend_Action_Loader {

	public function create() {
		return new GlobalLayoutStrings(
			\WPML_Gutenberg_Integration_Factory::createStringsInBlock( make( \WPML_Gutenberg_Config_Option::class ) )
		);
	}
}
