<?php

namespace WPML\PB\Elementor\Config\DynamicElements;

use WPML\FP\Obj;
use WPML\PB\Elementor\Helper\Path;
use function WPML\FP\compose;

class MegaMenu {

	public static function get() {
		$isMenuItem = Path::propEq( 'widgetType', 'mega-menu' );

		$itemLinkLens = compose(
			Obj::lensProp( 'settings' ),
			Obj::lensMappedProp( 'menu_items' ),
			Obj::lensPath( [ '__dynamic__', 'item_link' ] )
		);

		return [ $isMenuItem, $itemLinkLens, 'internal-url', 'post_id' ];
	}
}
