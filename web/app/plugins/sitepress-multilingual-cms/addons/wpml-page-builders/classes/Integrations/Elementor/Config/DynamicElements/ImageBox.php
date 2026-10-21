<?php

namespace WPML\PB\Elementor\Config\DynamicElements;

use WPML\FP\Obj;
use WPML\PB\Elementor\Helper\Path;

class ImageBox {

	public static function get() {
		$isImageBox        = Path::propEq( 'widgetType', 'image-box' );
		$ImageBoxLinksLens = Obj::lensPath( [ 'settings', '__dynamic__', 'link' ] );

		return [ $isImageBox, $ImageBoxLinksLens, 'internal-url', 'post_id' ];
	}
}
