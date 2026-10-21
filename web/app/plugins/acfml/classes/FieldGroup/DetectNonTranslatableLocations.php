<?php

namespace ACFML\FieldGroup;

use ACFML\Notice\Links;
use WPML\FP\Logic;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\LIB\WP\Post;

class DetectNonTranslatableLocations {

	const KEY = 'acfml_non_translatable_locations';

	const PROP_DISMISSED = 'dismissed';
	const PROP_HASH      = 'hash';

	const DETECTED_POST_TYPE    = 'post';
	const DETECTED_TAXONOMY     = 'taxonomy';
	const DETECTED_OPTIONS_PAGE = 'options_page';

	const LOCATION_PARAM_OPTIONS_PAGE = 'options_page';

	const OPTIONS_PAGE_PURE  = 'pure';
	const OPTIONS_PAGE_MIXED = 'mixed';

	public function process( $fieldGroup ) {
		$fieldGroupId = (int) Obj::prop( 'ID', $fieldGroup );
		$locations    = (array) Obj::prop( 'location', $fieldGroup );
		$hash         = md5( wp_json_encode( $locations ) );

		if ( self::get( $fieldGroupId, self::PROP_HASH ) !== $hash ) {
			self::updateAll(
				$fieldGroupId,
				[
					self::PROP_HASH      => $hash,
					self::PROP_DISMISSED => false,
				]
			);
		}
	}

	public static function detect( array $locations ) {
		if ( self::OPTIONS_PAGE_PURE === self::classifyOptionsPageLocations( $locations ) ) {
			return self::DETECTED_OPTIONS_PAGE;
		}

		$isNotPositiveComparison = Logic::complement( Relation::propEq( 'operator', '==' ) );
		$isPostType              = Relation::propEq( 'param', 'post_type' );
		$isTaxonomy              = Relation::propEq( 'param', 'taxonomy' );
		$getValue                = Obj::prop( 'value' );

		foreach ( $locations as $locationGroup ) {
			foreach ( $locationGroup as $location ) {
				if ( $isNotPositiveComparison( $location ) ) {
					continue;
				} elseif ( $isPostType( $location ) && ! apply_filters( 'wpml_is_translated_post_type', true, $getValue( $location ) ) ) {
					return self::DETECTED_POST_TYPE;
				} elseif ( $isTaxonomy( $location ) && ! apply_filters( 'wpml_is_translated_taxonomy', true, $getValue( $location ) ) ) {
					return self::DETECTED_TAXONOMY;
				}
			}
		}

		return null;
	}

	public static function classifyOptionsPageLocations( $locations ) {
		$isPositiveComparison = Relation::propEq( 'operator', '==' );
		$isOptionsPage        = Relation::propEq( 'param', self::LOCATION_PARAM_OPTIONS_PAGE );

		$totalGroups       = 0;
		$optionsPageGroups = 0;

		foreach ( (array) $locations as $locationGroup ) {
			++$totalGroups;

			foreach ( (array) $locationGroup as $location ) {
				if ( $isPositiveComparison( $location ) && $isOptionsPage( $location ) ) {
					++$optionsPageGroups;
					break;
				}
			}
		}

		if ( ! $optionsPageGroups ) {
			return null;
		}

		return $optionsPageGroups === $totalGroups
			? self::OPTIONS_PAGE_PURE
			: self::OPTIONS_PAGE_MIXED;
	}

	public static function getDetectedType( $fieldGroupId, array $locations ) {
		if ( Obj::prop( self::PROP_DISMISSED, self::getAll( $fieldGroupId ) ) ) {
			return null;
		}

		return self::detect( $locations );
	}

	public static function getTitle( $nonTranslatableType ) {
		return DetectNonTranslatableLocations::DETECTED_TAXONOMY === $nonTranslatableType
			/* translators: Title of the notice on a field group whose taxonomy cannot be translated yet. Verb phrase, imperative. */
			? esc_html__( 'Set translation preferences for the attached taxonomy', 'acfml' )
			/* translators: Title of the notice on a field group whose post type cannot be translated yet. Verb phrase, imperative. */
			: esc_html__( 'Set translation preferences for the attached post type', 'acfml' );
	}

	public static function getSettingsUrl( $nonTranslatableType ) {
		$section = DetectNonTranslatableLocations::DETECTED_TAXONOMY === $nonTranslatableType ? 'taxonomies' : 'post-types';

		return admin_url( 'admin.php?page=tm/menu/settings&section=' . $section );
	}

	public static function getDescription( $nonTranslatableType ) {
		return DetectNonTranslatableLocations::DETECTED_TAXONOMY === $nonTranslatableType
			/* translators: Body of the notice on a field group whose taxonomy cannot be translated yet. Keep the trailing space: a link follows on the same line. */
			? esc_html__( 'If you want to translate your fields, go to the WPML Settings page and make the taxonomy attached to this field group translatable. ', 'acfml' )
			: sprintf(
				/* translators: %1$s and %2$s will wrap the string in a <a> link html tag */
				esc_html__( 'If you want to translate your fields, go to the WPML Settings page and %1$smake the post type attached to this field group translatable%2$s.', 'acfml' ),
				'<a href="' . Links::getDocTranslatePostType() . '" class="wpml-external-link" target="_blank">',
				'</a>'
			);
	}

	public static function dismiss( $fieldGroupId ) {
		self::updateAll( $fieldGroupId, Obj::assoc( self::PROP_DISMISSED, true, self::getAll( $fieldGroupId ) ) );
	}

	private static function get( $fieldGroupId, $prop ) {
		return Obj::prop( $prop, self::getAll( $fieldGroupId ) );
	}

	private static function getAll( $fieldGroupId ) {
		return (array) Post::getMetaSingle( $fieldGroupId, self::KEY );
	}

	private static function updateAll( $fieldGroupId, $data ) {
		Post::updateMeta( $fieldGroupId, self::KEY, $data );
	}
}
