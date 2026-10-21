<?php

namespace ACFML\Helper;

use ACFML\FieldGroup\UnconfiguredGroups;
use WPML\API\Sanitize;
use WPML\FP\Obj;

class FieldGroup {

	const CPT         = 'acf-field-group';
	const SCREEN_SLUG = 'acf-field-group';

	public static function isScreen() {
		return acf_is_screen( self::SCREEN_SLUG );
	}

	public static function isListScreen() {
		global $pagenow;

		return 'edit.php' === $pagenow
			&& self::SCREEN_SLUG === Sanitize::stringProp( 'post_type', $_GET );
	}

	public static function getId( $id ) {
		$group = acf_get_field_group( $id );

		if ( $group ) {
			return (int) Obj::prop( 'ID', $group );
		} else {
			$parentId = (int) Obj::prop( 'ID', get_post_parent( $id ) );
			if ( $parentId ) {
				return self::getId( $parentId );
			}
		}

		return null;
	}

	public static function getKey( $key ) {
		$group = acf_get_field_group( $key );

		if ( $group ) {
			return Obj::prop( 'key', $group );
		}

		$field = acf_get_field( $key );
		if ( $field ) {
			$parentKey = Obj::prop( 'parent', $field );
			if ( $parentKey ) {
				return self::getKey( $parentKey );
			}
		}

		return null;
	}

	public static function isTranslatable() {
		return is_post_type_translated( self::CPT );
	}

	public static function hasFieldOfTypes( $id, $fieldTypes ) {
		$fieldsInGroup = acf_get_fields( $id );

		if ( empty( $fieldsInGroup ) ) {
			return false;
		}

		return (bool) wpml_collect( $fieldTypes )
			->first( function( $type ) use ( $fieldsInGroup ) {
				return Fields::containsType( $fieldsInGroup, $type );
			} );
	}

	public static function hasGroupMissingMode() {
		return UnconfiguredGroups::hasAny();
	}
}
