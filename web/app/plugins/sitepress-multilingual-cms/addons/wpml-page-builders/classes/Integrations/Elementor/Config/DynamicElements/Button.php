<?php

namespace WPML\PB\Elementor\Config\DynamicElements;

use WPML\FP\Obj;
use WPML\PB\Elementor\Helper\Path;

class Button {

	public static function get() {
		$isButton       = Path::propEq( 'widgetType', 'button' );
		$buttonLinkLens = Obj::lensPath( [ 'settings', '__dynamic__', 'link' ] );

		return [ $isButton, $buttonLinkLens, 'internal-url', 'post_id' ];
	}
}
