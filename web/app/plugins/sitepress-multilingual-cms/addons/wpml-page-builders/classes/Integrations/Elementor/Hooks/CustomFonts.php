<?php

namespace WPML\PB\Elementor\Hooks;

use WPML\LIB\WP\Hooks;
use WPML\PB\Helper\LanguageNegotiation;
use WPML\PB\Helper\OwnDomainUrls;

use function WPML\FP\spreadArgs;

class CustomFonts implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	const FONT_FACE_KEY = 'font_face';

	public function add_hooks() {
		if ( LanguageNegotiation::isUsingDomains() ) {
			Hooks::onFilter( 'option_elementor_fonts_manager_fonts' )
				->then( spreadArgs( [ $this, 'makeUrlsRelative' ] ) );
		}
	}

	public function makeUrlsRelative( $fonts ) {
		if ( ! is_array( $fonts ) ) {
			return $fonts;
		}

		$hosts = LanguageNegotiation::getOwnHosts();

		if ( ! $hosts ) {
			return $fonts;
		}

		foreach ( $fonts as $family => $font ) {
			if ( self::carriesFontFace( $font ) ) {
				$fonts[ $family ][ self::FONT_FACE_KEY ] = OwnDomainUrls::makeRelative(
					$font[ self::FONT_FACE_KEY ],
					$hosts
				);
			}
		}

		return $fonts;
	}

	private static function carriesFontFace( $font ) {
		return is_array( $font )
				&& isset( $font[ self::FONT_FACE_KEY ] )
				&& is_string( $font[ self::FONT_FACE_KEY ] );
	}
}
