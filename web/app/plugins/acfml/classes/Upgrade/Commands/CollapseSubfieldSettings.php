<?php

namespace ACFML\Upgrade\Commands;

use ACFML\FieldPreferences\FieldReferences;
use ACFML\FieldPreferences\SubfieldRules;

class CollapseSubfieldSettings implements Command {

	const DONE_OPTION = 'acfml_subfield_settings_collapsed';

	const MAP_INDEXES = [
		'post' => 'custom_fields_translation',
		'term' => 'custom_term_fields_translation',
	];

	public static function run() {
		if ( get_option( self::DONE_OPTION ) || ! self::areLocalFieldGroupsRegistered() ) {
			return;
		}

		if ( ! SubfieldRules::isCoreResolutionAvailable() || ! function_exists( 'wpml_delete_custom_field_preferences' ) ) {
			return;
		}

		$rules = SubfieldRules::buildRules();
		if ( null === $rules ) {
			return;
		}

		$translationManagement = wpml_load_core_tm();

		$settings = $translationManagement->get_settings();
		if ( ! is_array( $settings ) || ! $settings ) {
			return;
		}

		$definitionNames = SubfieldRules::definitionNames();

		$removable = [];
		foreach ( self::MAP_INDEXES as $type => $index ) {
			if ( empty( $translationManagement->settings[ $index ] ) || ! is_array( $translationManagement->settings[ $index ] ) ) {
				continue;
			}

			$map         = $translationManagement->settings[ $index ];
			$entryKeys   = array_filter(
				array_map( 'strval', array_keys( $map ) ),
				fn( $metaKey ) => ! SubfieldRules::isDefinitionKey( $metaKey, $definitionNames )
			);
			$collapsible = self::keysResolvedToTheSamePreference( $entryKeys, $map, $rules, $type );
			$orphaned    = self::keysOfDeletedFields( array_diff( $entryKeys, $collapsible ), $type );

			if ( $collapsible || $orphaned ) {
				$removable[ $type ] = array_values( array_merge( $collapsible, $orphaned ) );
			}
		}

		foreach ( $removable as $type => $names ) {
			wpml_delete_custom_field_preferences( $names, $type );
		}

		if ( self::entriesRemain( $removable ) ) {
			return;
		}

		update_option( self::DONE_OPTION, 1, false );
	}

	private static function areLocalFieldGroupsRegistered() {
		return did_action( 'acf/init' ) && ! doing_action( 'acf/init' );
	}

	private static function entriesRemain( $removable ) {
		if ( ! $removable ) {
			return false;
		}

		$settings = get_option( 'icl_sitepress_settings' );
		if ( ! is_array( $settings ) ) {
			return true;
		}
		$translationManagementSettings = isset( $settings['translation-management'] ) && is_array( $settings['translation-management'] )
			? $settings['translation-management']
			: [];

		foreach ( $removable as $type => $names ) {
			$index = self::MAP_INDEXES[ $type ];
			$map   = isset( $translationManagementSettings[ $index ] ) && is_array( $translationManagementSettings[ $index ] ) ? $translationManagementSettings[ $index ] : [];
			foreach ( $names as $name ) {
				if ( array_key_exists( $name, $map ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function keysResolvedToTheSamePreference( $metaKeys, $map, $rules, $type ) {
		$collapsible = [];
		foreach ( self::filterKeysMatchingAnyRule( $metaKeys, $rules ) as $metaKey ) {
			$ruleMode = SubfieldRules::resolveWithRules( $rules, $metaKey, $type );
			if ( null !== $ruleMode && (int) $map[ $metaKey ] === $ruleMode ) {
				$collapsible[] = $metaKey;
			}
		}

		return $collapsible;
	}

	private static function keysOfDeletedFields( $metaKeys, $type ) {
		$keysByReferenceMetaKey = [];
		foreach ( $metaKeys as $metaKey ) {
			$referenceMetaKey = 0 === strpos( $metaKey, '_' ) ? $metaKey : '_' . $metaKey;

			$keysByReferenceMetaKey[ $referenceMetaKey ][] = $metaKey;
		}

		$orphaned = [];
		foreach ( FieldReferences::fieldKeysByMetaKey( array_keys( $keysByReferenceMetaKey ), $type ) as $referenceMetaKey => $fieldReferences ) {
			if ( ! array_filter( $fieldReferences, fn( $fieldReference ) => FieldReferences::pointsToAnExistingField( $fieldReference ) ) ) {
				$orphaned = array_merge( $orphaned, $keysByReferenceMetaKey[ $referenceMetaKey ] );
			}
		}

		return $orphaned;
	}

	private static function filterKeysMatchingAnyRule( $metaKeys, $rules ) {
		$matched = [];
		foreach ( array_chunk( array_column( $rules, 'pattern' ), 50 ) as $patterns ) {
			$combined = '#^_?(?:' . implode( '|', $patterns ) . ')$#';
			$grepped  = preg_grep( $combined, $metaKeys );
			$matched  = array_merge( $matched, false === $grepped ? $metaKeys : $grepped );
		}

		return array_unique( $matched );
	}
}
