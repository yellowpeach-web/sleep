<?php

namespace WPML\TM\Settings;

use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;

class Repository {

	public static function getCustomFieldsToTranslate() {
		return array_values( array_filter(
			PreferenceResolver::namesByMode( ElementType::POST, WPML_TRANSLATE_CUSTOM_FIELD )
		) );
	}

	public static function getCustomFields() {
		$map = PreferenceResolver::map( ElementType::POST );
		if ( array_key_exists( '', $map ) || array_key_exists( '0', $map ) ) {
			$map = array_filter(
				$map,
				function ( $key ) {
					return (bool) $key;
				},
				ARRAY_FILTER_USE_KEY
			);
		}

		return $map;
	}
}