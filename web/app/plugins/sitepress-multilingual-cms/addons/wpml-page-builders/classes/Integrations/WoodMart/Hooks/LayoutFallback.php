<?php

namespace WPML\Compatibility\WoodMart\Hooks;

class LayoutFallback implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	const ELEMENT_TYPE = 'woodmart_layout';

	private $passedIds = [];

	public function add_hooks() {
		add_filter( 'wpml_object_id', [ $this, 'rememberPassedId' ], 9, 2 );
		add_filter( 'wpml_object_id', [ $this, 'fallBackToDefaultLanguageCopy' ], 11, 4 );
	}

	public function rememberPassedId( $elementId, $elementType = null ) {
		if ( self::ELEMENT_TYPE === $elementType ) {
			$this->passedIds[] = $elementId;
		}

		return $elementId;
	}

	public function fallBackToDefaultLanguageCopy( $elementId, $elementType = null, $returnOriginal = false, $languageCode = null ) {
		if ( self::ELEMENT_TYPE !== $elementType || ! $this->passedIds ) {
			return $elementId;
		}

		$passedId = array_pop( $this->passedIds );

		if ( $elementId || $returnOriginal || null !== $languageCode || ! is_numeric( $passedId ) || (int) $passedId <= 0 ) {
			return $elementId;
		}

		$defaultLanguageId = apply_filters( 'wpml_object_id', (int) $passedId, self::ELEMENT_TYPE, false, apply_filters( 'wpml_default_language', null ) );

		return is_numeric( $defaultLanguageId ) && (int) $defaultLanguageId > 0 ? (int) $defaultLanguageId : $elementId;
	}
}
