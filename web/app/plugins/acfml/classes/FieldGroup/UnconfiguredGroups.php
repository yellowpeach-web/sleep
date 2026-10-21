<?php

namespace ACFML\FieldGroup;

use ACFML\Helper\Fields;
use WPML\FP\Obj;
use WPML\FP\Relation;

class UnconfiguredGroups {

	const TRANSIENT_KEY = 'acfml_unconfigured_groups_inventory';

	const CACHE_TTL = 3600;

	public static function get() {
		$cached = get_transient( self::TRANSIENT_KEY );

		if ( is_array( $cached ) && isset( $cached['groupCount'], $cached['fieldCount'], $cached['postCount'] ) ) {
			return $cached;
		}

		$inventory = self::compute();
		set_transient( self::TRANSIENT_KEY, $inventory, self::CACHE_TTL );

		return $inventory;
	}

	public static function flush() {
		delete_transient( self::TRANSIENT_KEY );
	}

	public static function hasAny() {
		return (bool) self::getWritableUnconfigured();
	}

	private static function getWritableUnconfigured() {
		return wpml_collect( acf_get_field_groups() )
			->reject( Relation::propEq( 'ID', 0 ) )
			->filter(
				function ( $group ) {
					return ! Mode::isConfigured( $group );
				}
			)
			->filter(
				function ( $group ) {
					return ! SetupInventory::isJsonFileNewer( (array) $group );
				}
			)
			->values()
			->toArray();
	}

	private static function compute() {
		$groups = self::getWritableUnconfigured();

		if ( ! $groups ) {
			return [
				'groupCount' => 0,
				'fieldCount' => 0,
				'postCount'  => 0,
			];
		}

		$fieldCount = 0;
		$metaKeys   = [];

		foreach ( $groups as $group ) {
			$fields = acf_get_fields( $group );
			if ( ! $fields ) {
				continue;
			}

			Fields::iterate(
				$fields,
				function ( $field ) use ( &$fieldCount ) {
					if ( ! ModeValidity::storesNoValue( $field ) ) {
						++$fieldCount;
					}

					return $field;
				},
				function ( $layout ) {
					return $layout;
				}
			);

			foreach ( $fields as $field ) {
				$name = Obj::prop( 'name', $field );
				if ( is_string( $name ) && '' !== $name ) {
					$metaKeys[] = $name;
				}
			}
		}

		return [
			'groupCount' => count( $groups ),
			'fieldCount' => $fieldCount,
			'postCount'  => self::countPosts( array_values( array_unique( $metaKeys ) ) ),
		];
	}

	private static function countPosts( array $metaKeys ) {
		return AttachedPosts::countByMetaKeys( $metaKeys );
	}
}
