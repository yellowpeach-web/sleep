<?php

namespace ACFML\Options;

use ACFML\Field\CompositeValue;
use ACFML\Strings\Factory;
use ACFML\Strings\Package;
use WPML\FP\Obj;
use WPML\FP\Str;

class StringTranslationHooks implements \IWPML_Backend_Action, \IWPML_REST_Action {


	public function add_hooks() {
		add_action( 'wpml_st_add_string_translation', [ $this, 'saveOptionFieldTranslation' ], 10, 4 );
	}

	public function saveOptionFieldTranslation( $translationId, $translationData, $language, $stringId ) {
		if ( ICL_STRING_TRANSLATION_COMPLETE !== Obj::propOr( null, 'status', $translationData ) || ! array_key_exists( 'value', $translationData ) ) {
			return;
		}

		$string = apply_filters( 'wpml_st_get_string_by_id', null, (int) $stringId );
		if ( ! is_array( $string ) || empty( $string['string_package_id'] ) ) {
			return;
		}

		$optionFieldName = Str::match( '/^options-(.*?)-(field_.*?)-.*?$/', $string['name'] );
		if ( ! $optionFieldName || empty( $optionFieldName[1] ) || empty( $optionFieldName[2] ) ) {
			return;
		}

		$packageObject = Factory::createWpmlPackage( $string['string_package_id'] );
		if ( Package::OPTION_PACKAGE_KIND_SLUG !== Obj::prop( 'kind_slug', $packageObject ) || ! isset( $packageObject->name ) ) {
			return;
		}

		list( $fieldName, $subKey ) = CompositeValue::splitName( $optionFieldName[1] );

		$optionName      = $packageObject->name . '_' . $language . '_' . $fieldName;
		$translatedValue = TranslatedValue::toStore(
			$translationData['value'],
			$subKey,
			$packageObject->name . '_' . $fieldName,
			$optionName
		);

		if ( null === $translatedValue ) {
			return;
		}

		$optionKey = $optionFieldName[2];
		update_option( $optionName, $translatedValue );
		update_option( '_' . $optionName, $optionKey );
	}
}
