<?php

namespace WPML\PB\Elementor\Config\DynamicElements;

use WPML\FP\Obj;
use WPML\PB\Elementor\Helper\Path;

class FormPopup {

	public static function get() {
		$popupIdPath = [ 'settings', 'popup_action_popup_id' ];

		$isFormWithPopup = function ( $item ) use ( $popupIdPath ) {
			return Path::prop( 'widgetType', $item ) === 'form'
				&& Path::get( $popupIdPath, $item );
		};

		$popupIdLens = Obj::lensPath( $popupIdPath );

		return [ $isFormWithPopup, $popupIdLens ];
	}
}
