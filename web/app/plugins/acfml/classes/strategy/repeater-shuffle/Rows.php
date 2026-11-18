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

	public static function readWithNested( $entityId ) {
		$fields = get_field_objects( $entityId, false );
		$rows   = [];
		$nested = [];

		foreach ( is_array( $fields ) ? $fields : [] as $name => $field ) {
			self::collect( (string) $name, $field, $rows, true, $nested );
		}

		return [ $rows, $nested ];
	}

	public static function hashes( $entityId ) {
		return array_map( Obj::prop( 'rows' ), self::read( $entityId ) );
	}

	private static function collect( $prefix, $field, array &$rows, $readNested = false, array &$nested = [] ) {
		if ( Fields::isWrapper( $field ) ) {
			$rows[ $prefix ] = [
				'type' => (string) Obj::prop( 'type', $field ),
				'rows' => HashCalculator::calculateRows( TranslatableValue::of( $field ) ),
			];

			if ( $readNested ) {
				self::collectInsideRows( $prefix, $field, $nested );
			}

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
			self::collect( $prefix . '_' . $name, $subField, $rows, $readNested, $nested );
		}
	}

	private static function collectInsideRows( $prefix, array $field, array &$nested ) {
		$rows = Obj::prop( 'value', $field );

		if ( ! is_array( $rows ) ) {
			return;
		}

		$subFields     = (array) Obj::propOr( [], 'sub_fields', $field );
		$layoutsByName = Fields::getLayoutsByName( $field );

		foreach ( array_values( $rows ) as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$layout     = Obj::prop( Fields::LAYOUT_KEY, $row );
			$describing = $layout ? (array) Obj::pathOr( [], [ $layout, 'sub_fields' ], $layoutsByName ) : $subFields;

			foreach ( $describing as $subField ) {
				$key  = Obj::prop( 'key', $subField );
				$name = Obj::prop( 'name', $subField );

				if ( ! $key || ! $name || ! array_key_exists( $key, $row ) ) {
					continue;
				}

				$subField['value'] = $row[ $key ];
				self::collect( $prefix . '_' . $index . '_' . $name, $subField, $nested, true, $nested );
			}
		}
	}
}
