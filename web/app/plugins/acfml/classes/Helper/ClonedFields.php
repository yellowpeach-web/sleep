<?php

namespace ACFML\Helper;

class ClonedFields {

	const CLONE_TYPE = 'clone';

	const MAX_DEPTH = 64;

	public static function findSubField( array $cloneField, string $fieldKey, int $depth = 0 ): ?array {
		if ( $depth > self::MAX_DEPTH ) {
			return null;
		}

		foreach ( (array) ( $cloneField['sub_fields'] ?? [] ) as $subField ) {
			if ( ! is_array( $subField ) ) {
				continue;
			}
			if ( ( $subField['key'] ?? '' ) !== $fieldKey ) {
				$subField = self::CLONE_TYPE === ( $subField['type'] ?? '' )
					? self::findSubField( $subField, $fieldKey, $depth + 1 )
					: null;
			}
			if ( ! $subField ) {
				continue;
			}

			return self::inheritParentPreference( $cloneField, $subField );
		}

		return null;
	}

	public static function inheritParentPreference( array $parentField, array $subField ): array {
		$parentPreference = isset( $parentField['wpml_cf_preferences'] )
			? (int) $parentField['wpml_cf_preferences']
			: WPML_TRANSLATE_CUSTOM_FIELD;
		if ( WPML_TRANSLATE_CUSTOM_FIELD !== $parentPreference ) {
			$subField['wpml_cf_preferences'] = $parentPreference;
		}

		return $subField;
	}
}
