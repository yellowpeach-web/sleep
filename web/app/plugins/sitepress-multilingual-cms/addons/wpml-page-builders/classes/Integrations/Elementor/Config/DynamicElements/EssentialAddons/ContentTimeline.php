<?php

namespace WPML\PB\Elementor\Config\DynamicElements\EssentialAddons;

use WPML\FP\Obj;
use WPML\PB\Elementor\Helper\Path;
use function WPML\FP\compose;

class ContentTimeline {

	public static function get() {
		$isEAContentTimeline = Path::propEq( 'widgetType', 'eael-content-timeline' );

		$contentTimelineLinksLens = compose(
			Obj::lensProp( 'settings' ),
			Obj::lensMappedProp( 'eael_coustom_content_posts' ),
			Obj::lensPath( [ '__dynamic__', 'eael_read_more_text_link' ] )
		);

		return [ $isEAContentTimeline, $contentTimelineLinksLens, 'popup', 'popup' ];
	}
}
