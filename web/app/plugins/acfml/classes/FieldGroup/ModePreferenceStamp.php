<?php

namespace ACFML\FieldGroup;

use ACFML\Helper\Fields;
use WPML\FP\Fns;
use WPML\FP\Obj;

class ModePreferenceStamp {

	public static function onFields( array $fieldGroup, $mode ) {
		$fields = Obj::prop( 'fields', $fieldGroup );

		if ( Mode::ADVANCED === $mode || ! in_array( $mode, Mode::ALL, true ) || ! is_array( $fields ) || ! $fields ) {
			return $fieldGroup;
		}

		$fieldGroup['fields'] = Fields::iterate( $fields, self::stamp( $mode ), Fns::identity() );

		return $fieldGroup;
	}

	private static function stamp( $mode ) {
		$getModePreference = ModeDefaults::get( $mode );

		return function ( $field ) use ( $getModePreference ) {
			if ( isset( $field['wpml_cf_preferences'] ) || ModeValidity::storesNoValue( $field ) ) {
				return $field;
			}

			$field['wpml_cf_preferences'] = $getModePreference( $field );

			return $field;
		};
	}
}
