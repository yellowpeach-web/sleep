<?php

namespace WPML\Compatibility\FusionBuilder\Hooks;

use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class MegaMenuSync implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const META_KEY    = '_menu_item_fusion_megamenu';
	const ELEMENT_KEY = 'select';

	public function add_hooks() {
		Hooks::onFilter( 'wpml_sync_custom_field_copied_value', 10, 4 )
			->then( spreadArgs( [ $this, 'translateSelectedElementId' ] ) );
	}

	public function translateSelectedElementId( $value, $postIdFrom, $postIdTo, $metaKey ) {
		if ( self::META_KEY !== $metaKey || ! is_array( $value ) || ! isset( $value[ self::ELEMENT_KEY ] ) ) {
			return $value;
		}

		$elementId = filter_var( $value[ self::ELEMENT_KEY ], FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );
		if ( false === $elementId ) {
			return $value;
		}

		$elementType = get_post_type( $elementId );
		if ( ! is_string( $elementType ) || '' === $elementType ) {
			return $value;
		}

		$targetLanguage = apply_filters(
			'wpml_element_language_code',
			null,
			[ 'element_id' => $postIdTo, 'element_type' => 'post_nav_menu_item' ]
		);
		if ( ! $targetLanguage ) {
			return $value;
		}

		$translatedId = apply_filters( 'wpml_object_id', $elementId, $elementType, false, $targetLanguage );

		if ( $translatedId ) {
			$value[ self::ELEMENT_KEY ] = (string) $translatedId;
		}

		return $value;
	}
}
