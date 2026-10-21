<?php

namespace ACFML\FieldGroup;

use ACFML\Helper\AcfPostId;
use WPML\API\Sanitize;
use WPML\FP\Obj;
use WPML\FP\Relation;

class Mode {

	const KEY = 'acfml_field_group_mode';

	const TRANSLATION  = 'translation';
	const LOCALIZATION = 'localization';
	const ADVANCED     = 'advanced';

	const MIXED = 'mixed';

	const ALL = [ self::TRANSLATION, self::LOCALIZATION, self::ADVANCED ];

	const ENTITY_POST     = 'post';
	const ENTITY_TAXONOMY = 'taxonomy';
	const ENTITY_OPTION   = 'option';

	public static function getMode( $fieldGroup ) {
		return Obj::prop( self::KEY, $fieldGroup );
	}

	public static function isConfigured( $fieldGroup ) {
		$mode = self::getMode( $fieldGroup );

		return is_string( $mode ) && '' !== $mode;
	}

	public static function getLabels() {
		return [
			/* translators: Name of the field group translation mode where each field's preference is set by hand. Noun used as a mode name. */
			self::ADVANCED     => __( 'Expert', 'acfml' ),
			/* translators: Name of the field group translation mode where the fields are sent for translation. */
			self::TRANSLATION  => __( 'Same content in every language, translated', 'acfml' ),
			/* translators: Name of the field group translation mode where every language keeps its own field values. */
			self::LOCALIZATION => __( 'Each language has its own content', 'acfml' ),
		];
	}

	public static function getLabel( $mode ) {
		return Obj::propOr( '', $mode ?: self::ADVANCED, self::getLabels() );
	}

	private static function is( $mode, $fieldGroup ) {
		return Relation::equals( $mode, self::getMode( $fieldGroup ) );
	}

	public static function isAdvanced( $fieldGroup ) {
		return self::is( self::ADVANCED, $fieldGroup )
			|| self::is( null, $fieldGroup );
	}

	public static function getForFieldableEntity( $entityType = null, $id = null ) {
		$filter = self::getFieldGroupsFilter( $entityType, $id );

		if ( $filter ) {
			return self::getForFieldGroups( acf_get_field_groups( $filter ) );
		}

		if ( self::ENTITY_OPTION === $entityType && $id ) {
			return self::getForFieldGroups( self::getOptionsPagesFieldGroups( (string) $id ) );
		}

		return null;
	}

	private static function getFieldGroupsFilter( $entityType, $id ) {
		switch ( $entityType ) {
			case self::ENTITY_POST:
				return [ 'post_id' => $id ?: Sanitize::stringProp( 'post', $_REQUEST ) ];
			case self::ENTITY_TAXONOMY:
				return [ 'taxonomy' => AcfPostId::getTermTaxonomy( $id ) ?: Sanitize::stringProp( 'taxonomy', $_REQUEST ) ];
			case self::ENTITY_OPTION:
				return $id ? [] : [ 'options_page' => Sanitize::stringProp( 'page', $_REQUEST ) ];
			default:
				return [];
		}
	}

	private static function getOptionsPagesFieldGroups( $optionsId ) {
		$getFieldGroups = function ( $optionsPage ) {
			return acf_get_field_groups( [ 'options_page' => Obj::prop( 'menu_slug', $optionsPage ) ] );
		};

		return wpml_collect( function_exists( 'acf_get_options_pages' ) ? (array) acf_get_options_pages() : [] )
			->filter( Relation::propEq( 'post_id', $optionsId ) )
			->map( $getFieldGroups )
			->flatten( 1 )
			->unique( 'key' )
			->values()
			->toArray();
	}

	public static function getForFieldGroups( $fieldGroups ) {
		if ( ! $fieldGroups ) {
			return null;
		}

		$modes = wpml_collect( $fieldGroups )
			->map( fn( $fieldGroup ) => self::getModeOrDefault( $fieldGroup ) )
			->unique()
			->values();

		return 1 === $modes->count() ? $modes->first() : self::MIXED;
	}

	public static function getModeOrDefault( $fieldGroup ): string {
		return self::isConfigured( $fieldGroup ) ? self::getMode( $fieldGroup ) : self::ADVANCED;
	}
}
