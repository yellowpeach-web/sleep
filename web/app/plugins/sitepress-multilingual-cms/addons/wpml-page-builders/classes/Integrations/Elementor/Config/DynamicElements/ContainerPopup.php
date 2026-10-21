<?php

namespace WPML\PB\Elementor\Config\DynamicElements;

use WPML\FP\Obj;
use WPML\PB\Elementor\Helper\Path;
use function WPML\FP\compose;


class ContainerPopup {

	public static function get() {
		$isContainerPopup = Path::propEq( 'elType', 'container' );
	
		$containerLinksLens = compose(
			Obj::lensProp( 'settings' ),
			Obj::lensPath( [ '__dynamic__', 'link' ] )
		);
		return [ $isContainerPopup, $containerLinksLens, 'popup', 'popup' ];
	}
}
