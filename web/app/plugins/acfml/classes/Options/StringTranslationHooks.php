<?php

namespace ACFML\Options;

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

		global $wpdb;
		$string = $wpdb->get_row( $wpdb->prepare( "SELECT name, string_package_id FROM {$wpdb->prefix}icl_strings WHERE id = %d", $stringId ) );
		if ( ! $string || ! $string->string_package_id ) {
			return;
		}

		$optionFieldName = Str::match( '/^options-(.*?)-(field_.*?)-.*?$/', $string->name );
		if ( ! $optionFieldName || empty( $optionFieldName[1] ) || empty( $optionFieldName[2] ) ) {
			return;
		}

		$packageObject = Factory::createWpmlPackage( $string->string_package_id );
		if ( Package::OPTION_PACKAGE_KIND_SLUG !== Obj::prop( 'kind_slug', $packageObject ) || ! isset( $packageObject->name ) ) {
			return;
		}

		$optionField = $optionFieldName[1];
		$optionKey   = $optionFieldName[2];
		$optionName  = $packageObject->name . '_' . $language . '_' . $optionField;
		update_option( $optionName, $translationData['value'] );
		update_option( '_' . $optionName, $optionKey );
	}
}
