<?php

namespace WPML\PB\Elementor\Config\DynamicElements;

use WPML\FP\Obj;
use WPML\PB\Elementor\Helper\Path;
use function WPML\FP\compose;

class Hotspot{

	public static function get() {
		$isHotspot = Path::propEq( 'widgetType', 'hotspot' );
		
		$hotspotLinksLens = compose(
			Obj::lensProp( 'settings' ),
			Obj::lensMappedProp( 'hotspot' ),
			Obj::lensPath( [ '__dynamic__', 'hotspot_link' ] )
		);

		return [ $isHotspot, $hotspotLinksLens, 'popup', 'popup' ];
	}
}
