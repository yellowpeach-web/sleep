<?php

namespace WPML\PB\Elementor\Config\DynamicElements;

use WPML\FP\Obj;
use WPML\PB\Elementor\Helper\Path;

class Lottie {

	public static function get() {
		$isLottie        = Path::propEq( 'widgetType', 'lottie' );
		$lottieLinksLens = Obj::lensPath( [ 'settings', '__dynamic__', 'custom_link' ] );

		return [ $isLottie, $lottieLinksLens, 'popup', 'popup' ];
	}
}
