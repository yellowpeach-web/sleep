<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\API\PostTypes;
use WPML\Core\SharedKernel\Component\Post\Domain\TranslateEverythingScope;

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
		return array_values(
			array_filter(
				TranslateEverythingScope::offeredOnly( PostTypes::getAutomaticTranslatable() ),
				function ( $postType ) {
					return ! Cutoff::isSkip( OfferedTypeSinceDate::resolve( $postType ) );
				}
			)
		);
	}
}
