<?php

namespace WPML\Compatibility\WoodMart\Hooks;

class LayoutUnpublishedFallback implements \IWPML_Frontend_Action {

	const ELEMENT_TYPE = 'woodmart_layout';

	public function add_hooks() {
		add_filter( 'wpml_object_id', [ $this, 'fallBackWhenNotPublished' ], 12, 4 );
	}

	public function fallBackWhenNotPublished( $elementId, $elementType = null, $returnOriginal = false, $languageCode = null ) {
		if ( self::ELEMENT_TYPE !== $elementType || ! is_numeric( $elementId ) || (int) $elementId <= 0 || $returnOriginal || null !== $languageCode ) {
			return $elementId;
		}

		$status = get_post_status( (int) $elementId );

		if ( 'publish' === $status || false === $status ) {
			return $elementId;
		}

		$defaultLanguageId = apply_filters( 'wpml_object_id', (int) $elementId, self::ELEMENT_TYPE, false, apply_filters( 'wpml_default_language', null ) );

		return is_numeric( $defaultLanguageId ) && (int) $defaultLanguageId > 0 && (int) $defaultLanguageId !== (int) $elementId
			? (int) $defaultLanguageId
			: $elementId;
	}
}
