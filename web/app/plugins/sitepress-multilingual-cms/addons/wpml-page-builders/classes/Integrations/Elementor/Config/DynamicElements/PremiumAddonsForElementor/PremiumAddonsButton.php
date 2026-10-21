<?php

namespace WPML\PB\Elementor\Config\DynamicElements\PremiumAddonsForElementor;

use WPML\FP\Obj;
use WPML\PB\Elementor\Helper\Path;
use function WPML\FP\compose;

class PremiumAddonsButton {

	public static function get() {
		$isButton = Path::propEq( 'widgetType', 'premium-addon-button' );

		$buttonLinkLens = compose(
			Obj::lensProp( 'settings' ),
			Obj::lensPath( [ '__dynamic__', 'premium_button_link' ] )
		);

		return [ $isButton, $buttonLinkLens, 'popup', 'popup' ];
	}
}
