<?php

namespace WPML\PB\SiteOrigin\Hooks;

use function WPML\Container\make;

class LayoutBlockNestedLayoutsFactory implements \IWPML_Backend_Action_Loader, \IWPML_Frontend_Action_Loader {

	public function create() {
		return new LayoutBlockNestedLayouts(
			\WPML_Gutenberg_Integration_Factory::createStringsInBlock( make( \WPML_Gutenberg_Config_Option::class ) )
		);
	}
}
