<?php

namespace WPML\PB\Elementor\Config\DynamicElements;

use WPML\FP\Obj;
use WPML\PB\Elementor\Helper\Path;

class LoopCarousel {

	public static function get() {
		$loopCarouselIdPath = [ 'settings', 'template_id' ];

		$hasLoopCarousel = function ( $item ) use ( $loopCarouselIdPath ) {
			return Path::prop( 'widgetType', $item ) === 'loop-carousel'
				&& Path::get( $loopCarouselIdPath, $item );
		};

		$loopCarouselIdLens = Obj::lensPath( $loopCarouselIdPath );

		return [ $hasLoopCarousel, $loopCarouselIdLens ];
	}
}
