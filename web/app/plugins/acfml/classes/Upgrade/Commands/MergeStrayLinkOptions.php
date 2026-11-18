<?php

namespace ACFML\Upgrade\Commands;

use ACFML\Field\CompositeValue;
use ACFML\Options;
use ACFML\Options\TranslatedValue;
use ACFML\Strings\Package;
use WPML\LIB\WP\Hooks;

class MergeStrayLinkOptions implements Command {

	const KEY = 'stray-link-options-merged';

	public static function run() {
		Hooks::onAction( 'wp_loaded' )
			->then( function() {
				if ( Options::get( self::KEY ) ) {
					return;
				}

				$strayOptionNames = self::getStrayOptionNames();
				$namespaces       = $strayOptionNames ? self::getOptionsNamespaces() : [];
				foreach ( $strayOptionNames as $strayOptionName ) {
					self::mergeStrayOption( $strayOptionName, $namespaces );
				}

				Options::set( self::KEY, true );
			} );
	}

	private static function mergeStrayOption( $strayOptionName, $namespaces ) {
		$fieldKey = get_option( '_' . $strayOptionName );
		$field    = is_string( $fieldKey ) ? acf_get_field( $fieldKey ) : null;
		if ( ! $field ) {
			return;
		}

		list( $optionName, $subKey ) = CompositeValue::splitName( $strayOptionName );

		$namespace = self::findNamespace( $optionName, $namespaces );
		$flatName  = null === $namespace ? null : self::getFlatName( substr( $optionName, strlen( $namespace ) + 1 ), $field['name'] );
		if ( null === $flatName || ! in_array( $subKey, CompositeValue::getSubKeys( $field ), true ) ) {
			return;
		}

		$sourceOptionName = $namespace . '_' . $flatName;
		if ( ! self::languageHoldsOwnTranslation( $optionName, $sourceOptionName, $subKey ) ) {
			$translatedValue = TranslatedValue::toStore( get_option( $strayOptionName ), $subKey, $sourceOptionName, $optionName );
			if ( null === $translatedValue ) {
				return;
			}

			update_option( $optionName, $translatedValue );
			update_option( '_' . $optionName, $fieldKey );
		}

		delete_option( $strayOptionName );
		delete_option( '_' . $strayOptionName );
	}

	private static function getFlatName( $languageAndFlatName, $fieldName ) {
		$separatorPosition = strpos( $languageAndFlatName, '_' );
		$flatName          = false === $separatorPosition ? '' : substr( $languageAndFlatName, $separatorPosition + 1 );

		$isTheField    = $flatName === $fieldName;
		$isARowOfField = substr( $flatName, -strlen( '_' . $fieldName ) ) === '_' . $fieldName;

		return $isTheField || $isARowOfField ? $flatName : null;
	}

	private static function languageHoldsOwnTranslation( $optionName, $sourceOptionName, $subKey ) {
		$languageValue = get_option( $optionName );
		$sourceValue   = get_option( $sourceOptionName );

		return isset( $languageValue[ $subKey ] )
			&& ( ! isset( $sourceValue[ $subKey ] ) || $languageValue[ $subKey ] !== $sourceValue[ $subKey ] );
	}

	private static function findNamespace( $optionName, $namespaces ) {
		$matchingNamespaces = array_filter(
			$namespaces,
			fn( $namespace ) => 0 === strpos( $optionName, $namespace . '_' )
		);
		usort( $matchingNamespaces, fn( $first, $second ) => strlen( $second ) - strlen( $first ) );

		return $matchingNamespaces ? $matchingNamespaces[0] : null;
	}

	private static function getOptionsNamespaces() {
		global $wpdb;

		$packagesTable = $wpdb->prefix . 'icl_string_packages';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $packagesTable ) ) ) !== $packagesTable ) {
			return [];
		}

		return (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT name FROM {$wpdb->prefix}icl_string_packages WHERE kind_slug = %s",
				Package::OPTION_PACKAGE_KIND_SLUG
			)
		);
	}

	private static function getStrayOptionNames() {
		global $wpdb;

		$subKeys    = array_unique( array_merge( ...array_values( CompositeValue::SUB_KEYS ) ) );
		$conditions = implode( ' OR ', array_fill( 0, count( $subKeys ), 'option_name LIKE %s' ) );
		$patterns   = array_map(
			fn( $subKey ) => '%' . $wpdb->esc_like( CompositeValue::SEPARATOR . $subKey ),
			$subKeys
		);

		$optionNames = (array) $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE {$conditions}", $patterns ) );

		return array_values( array_filter( $optionNames, fn( $optionName ) => 0 !== strpos( $optionName, '_' ) ) );
	}
}
