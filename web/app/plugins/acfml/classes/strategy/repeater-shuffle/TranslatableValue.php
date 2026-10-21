<?php

namespace ACFML\Repeater\Shuffle;

use ACFML\Helper\Fields;
use WPML\FP\Obj;
use WPML\FP\Relation;

class TranslatableValue {

	public static function of( array $field ) {
		$rows = Obj::prop( 'value', $field );

		if ( ! is_array( $rows ) ) {
			return $rows;
		}

		$subFields     = (array) Obj::propOr( [], 'sub_fields', $field );
		$layoutsByName = Fields::getLayoutsByName( $field );

		return array_map(
			fn( $row ) => is_array( $row )
				? self::reduceRow( $row, self::subFieldsDescribing( $row, $subFields, $layoutsByName ) )
				: $row,
			$rows
		);
	}

	private static function subFieldsDescribing( array $row, array $subFields, array $layoutsByName ): array {
		$layout = Obj::prop( Fields::LAYOUT_KEY, $row );

		return $layout
			? (array) Obj::pathOr( [], [ $layout, 'sub_fields' ], $layoutsByName )
			: $subFields;
	}

	private static function reduceRow( array $row, array $subFields ): array {
		$describing = array_filter(
			$subFields,
			fn( $subField ) => array_key_exists( (string) Obj::prop( 'key', $subField ), $row )
		);

		if ( ! $describing ) {
			return $row;
		}

		$reduced = self::layoutOf( $row );

		foreach ( $describing as $subField ) {
			$key = $subField['key'];

			if ( Fields::isWrapperOrGroup( $subField ) ) {
				$reduced[ $key ] = self::reduceSubValue( $row[ $key ], $subField );
			} elseif ( self::isTranslatedOrUnstamped( $subField ) ) {
				$reduced[ $key ] = $row[ $key ];
			}
		}

		return $reduced;
	}

	private static function layoutOf( array $row ): array {
		$layout = Obj::prop( Fields::LAYOUT_KEY, $row );

		return $layout ? [ Fields::LAYOUT_KEY => $layout ] : [];
	}

	private static function reduceSubValue( $value, array $subField ) {
		if ( Relation::propEq( 'type', 'group', $subField ) ) {
			return is_array( $value )
				? self::reduceRow( $value, (array) Obj::propOr( [], 'sub_fields', $subField ) )
				: $value;
		}

		return self::of( array_merge( $subField, [ 'value' => $value ] ) );
	}

	private static function isTranslatedOrUnstamped( array $subField ): bool {
		$preference = Obj::prop( 'wpml_cf_preferences', $subField );

		return null === $preference || WPML_TRANSLATE_CUSTOM_FIELD === (int) $preference;
	}
}
