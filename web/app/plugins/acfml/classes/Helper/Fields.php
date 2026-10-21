<?php

namespace ACFML\Helper;

use WPML\FP\Relation;
use WPML\FP\Obj;

class Fields {

	const WRAPPER_FIELDS = [ 'repeater', 'flexible_content' ];
	const LAYOUT_KEY         = 'acf_fc_layout';
	const LAYOUT_META_SUFFIX = '_layout_meta';
	const ROW_INDEX_PATTERN  = '_\d+_';

	public static function iterate( $fields, $transformField, $transformLayout, $fieldBasePattern = '' ) {
		foreach ( $fields as &$field ) {
			$fieldPattern = $fieldBasePattern . preg_quote( $field['name'] );
			$field        = $transformField( $field, $fieldPattern );

			if ( isset( $field['sub_fields'] ) ) {
				$field['sub_fields'] = self::iterate( $field['sub_fields'], $transformField, $transformLayout, self::getSubfieldBasePattern( $field, $fieldPattern, $fieldBasePattern ) );
			}

			if ( isset( $field['layouts'] ) ) {
				foreach ( $field['layouts'] as &$layout ) {
					$layout = $transformLayout( $layout );

					if ( isset( $layout['sub_fields'] ) ) {
						$layout['sub_fields'] = self::iterate( $layout['sub_fields'], $transformField, $transformLayout, $fieldPattern . self::ROW_INDEX_PATTERN );
					}
				}
			}
		}

		return $fields;
	}

	private static function getSubfieldBasePattern( $field, $fieldPattern, $fieldBasePattern ) {
		switch ( Obj::prop( 'type', $field ) ) {
			case 'clone':
				return $fieldBasePattern;
			case 'group':
				return $fieldPattern . '_';
			default:
				return $fieldPattern . self::ROW_INDEX_PATTERN;
		}
	}

	public static function getFresh( array $fieldGroup ): array {
		self::evictFromStore( acf_get_store( 'fields' ), $fieldGroup['ID'] );

		return acf_get_fields( $fieldGroup );
	}

	private static function evictFromStore( $store, $parentId ) {
		foreach ( acf_get_raw_fields( $parentId ) as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$store->remove( $field['key'] );
			self::evictFromStore( $store, $field['ID'] );
		}
	}

	public static function containsType( $fields, $type ) {
		$isGroupContainingIt = function( $field ) use ( $type ) {
			return Relation::propEq( 'type', 'group', $field )
				&& self::containsType( (array) Obj::propOr( [], 'sub_fields', $field ), $type );
		};

		$isTypeOrGroupContainingIt = function( $field ) use ( $type, $isGroupContainingIt ) {
			return Relation::propEq( 'type', $type, $field ) || $isGroupContainingIt( $field );
		};

		return (bool) wpml_collect( $fields )
			->first( $isTypeOrGroupContainingIt );
	}

	public static function isWrapper( $field ) {
		return in_array(
			Obj::prop( 'type', $field ),
			self::WRAPPER_FIELDS,
			true
		);
	}

	public static function isWrapperOrGroup( $field ) {
		return in_array(
			Obj::prop( 'type', $field ),
			array_merge( [ 'group' ], self::WRAPPER_FIELDS ),
			true
		);
	}

	public static function getLayoutsByName( array $field ): array {
		return array_column( (array) Obj::propOr( [], 'layouts', $field ), null, 'name' );
	}
}
