<?php

namespace WPML\Compatibility\Divi\V5;

use function WPML\Container\make;

class LocalCanvasStringsFactory implements \IWPML_Backend_Action_Loader, \IWPML_Frontend_Action_Loader {

	public function create() {
		global $sitepress, $wpdb;

		if ( ! class_exists( '\WPML_ST_String_Factory' ) ) {
			return null;
		}

		$gutenberg = new \WPML_Gutenberg_Integration_Factory();

		return new LocalCanvasStrings(
			\WPML_Gutenberg_Integration_Factory::createStringsInBlock( make( \WPML_Gutenberg_Config_Option::class ) ),
			$gutenberg->create_gutenberg_integration(),
			new LocalCanvasRepository( $sitepress ),
			new \WPML_ST_String_Factory( $wpdb )
		);
	}
}
