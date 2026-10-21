<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\API\PostTypes;
use WPML\Core\SharedKernel\Component\Post\Domain\TranslateEverythingScope;
use WPML\Setup\Option;

class TranslatableTaxonomies {

	public static function get(): array {
		global $sitepress, $wp_taxonomies;

		if ( ! $sitepress || ! is_array( $wp_taxonomies ) ) {
			return [];
		}

		$translatable = [];

		foreach ( $wp_taxonomies as $taxonomy => $object ) {
			if ( $sitepress->is_translated_taxonomy( $taxonomy ) ) {
				$translatable[] = (string) $taxonomy;
			}
		}

		return $translatable;
	}

	public static function getEligibleForTea(): array {
		$translatedByTea = self::getPostTypesTranslatedByTea();

		$bound = array_values(
			array_filter(
				self::get(),
				function ( $taxonomy ) use ( $translatedByTea ) {
					return self::isBoundToAnyOf( $taxonomy, $translatedByTea );
				}
			)
		);

		$eligible = apply_filters( 'wpml_tea_translatable_taxonomies', $bound );

		return is_array( $eligible ) ? array_values( array_filter( $eligible, 'is_string' ) ) : [];
	}

	private static function isBoundToAnyOf( string $taxonomy, array $postTypes ): bool {
		$taxonomyObject = get_taxonomy( $taxonomy );

		if ( ! $taxonomyObject || ! isset( $taxonomyObject->object_type ) || ! is_array( $taxonomyObject->object_type ) ) {
			return false;
		}

		return (bool) array_intersect( $taxonomyObject->object_type, $postTypes );
	}

	private static function getPostTypesTranslatedByTea(): array {
		$automaticTranslatable = TranslateEverythingScope::offeredOnly( PostTypes::getAutomaticTranslatable() );

		if ( empty( Option::getTranslateEverythingPostsSinceDates() ) ) {
			return array_values( $automaticTranslatable );
		}

		return array_values(
			array_filter(
				$automaticTranslatable,
				function ( $postType ) {
					return Option::getTranslateEverythingPostSinceDate( $postType ) !== Option::SINCE_DATE_SKIP_TYPE;
				}
			)
		);
	}
}
