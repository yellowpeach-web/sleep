<?php

namespace WPML\Compatibility\WPBakery;

use WPML\PB\Media\CssImages;

class StylesFactory implements \IWPML_Backend_Action_Loader, \IWPML_Frontend_Action_Loader {

	public function create() {
		return new Styles(
			defined( 'WPML_MEDIA_VERSION' ) ? new CssImages( \WPML_Page_Builders_Media_Translate::create() ) : null
		);
	}
}
