<?php

namespace WPML\PB\Elementor\Config\DynamicElements;

use WPML\FP\Obj;
use WPML\PB\Elementor\Helper\Path;

class LoopGrid {

	public static function get() {
		$loopIdPath = [ 'settings', 'template_id' ];

		$hasLoop = function ( $item ) use ( $loopIdPath ) {
			return Path::prop( 'widgetType', $item ) === 'loop-grid'
				&& Path::get( $loopIdPath, $item );
		};

		$loopIdLens = Obj::lensPath( $loopIdPath );

		return [ $hasLoop, $loopIdLens ];
	}
}
