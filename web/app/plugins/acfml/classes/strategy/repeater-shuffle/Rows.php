<?php

namespace ACFML\Repeater\Shuffle;

use ACFML\Helper\Fields;
use ACFML\Helper\HashCalculator;
use WPML\FP\Obj;
use WPML\FP\Relation;

class Rows {

	public static function read( $entityId ) {
		$fields = get_field_objects( $entityId, false );
		$rows   = [];

		foreach ( is_array( $fields ) ? $fields : [] as $name => $field ) {
			self::collect( (string) $name, $field, $rows );
		}

		return $rows;
	}

	public static function hashes( $entityId ) {
		return array_map( Obj::prop( 'rows' ), self::read( $entityId ) );
	}

	private static function collect( $prefix, $field, array &$rows ) {
		if ( Fields::isWrapper( $field ) ) {
			$rows[ $prefix ] = [
				'type' => (string) Obj::prop( 'type', $field ),
				'rows' => HashCalculator::calculateRows( TranslatableValue::of( $field ) ),
			];

			return;
		}

		if ( ! Relation::propEq( 'type', 'group', $field ) ) {
			return;
		}

		foreach ( (array) Obj::propOr( [], 'sub_fields', $field ) as $subField ) {
			$name = Obj::prop( 'name', $subField );

			if ( ! $name ) {
				continue;
			}

			$subField['value'] = Obj::path( [ 'value', Obj::prop( 'key', $subField ) ], $field );
			self::collect( $prefix . '_' . $name, $subField, $rows );
		}
	}
}
