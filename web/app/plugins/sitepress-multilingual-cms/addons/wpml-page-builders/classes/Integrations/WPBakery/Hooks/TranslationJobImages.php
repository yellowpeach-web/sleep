<?php

namespace WPML\Compatibility\WPBakery\Hooks;

use WPML\FP\Lst;
use WPML\LIB\WP\Hooks;
use WPML\PB\TranslationJob\Groups;

use function WPML\FP\spreadArgs;

class TranslationJobImages implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	public function add_hooks() {
		Hooks::onFilter( 'wpml_pb_shortcode_string_title', 10, 2 )
			->then( spreadArgs( [ $this, 'filterStringTitle' ] ) );
		Hooks::onFilter( 'wpml_pb_image_module_patterns' )
			->then( spreadArgs( Lst::append( '/(?:hoverbox|single_image)-\d+-(\d+)$/' ) ) );
	}

	public function filterStringTitle( $title, $shortcode ) {
		$atts = shortcode_parse_atts( '[dummy ' . $shortcode['attributes'] . ']' );
		$imageId = isset( $atts['image'] ) ? $this->getImageId( $atts['image'] ) : 0;
		if ( $imageId && Groups::isGroupLabel( $title ) ) {
			$title = Groups::appendImageIdToGroupLabel( $title, $imageId );
		}

		return $title;
	}

	private function getImageId( string $image ): int {
		if ( is_numeric( $image ) ) {
			return (int) $image;
		}

		$links = \WPML\Compatibility\WPBakery\ImageLinks::decodeMap( $image );

		return $links ? (int) key( $links ) : 0;
	}
}
