<?php

namespace ACFML\Field;

use WPML\FP\Obj;

class RelationalIds {

	const POST_FIELD_TYPES = [ 'post_object', 'page_link', 'relationship', 'gallery' ];
	const TERM_FIELD_TYPES = [ 'taxonomy' ];

	public static function isRelational( array $field ): bool {
		return in_array( Obj::prop( 'type', $field ), array_merge( self::POST_FIELD_TYPES, self::TERM_FIELD_TYPES ), true );
	}

	public static function holdsTermIds( array $field ): bool {
		return in_array( Obj::prop( 'type', $field ), self::TERM_FIELD_TYPES, true );
	}

	public static function getTranslatedId( int $id, array $field, string $languageCode ) {
		$elementType = self::getElementType( $id, $field );
		if ( ! $elementType ) {
			return null;
		}

		$translatedId = apply_filters( 'wpml_object_id', $id, $elementType, false, $languageCode );

		return $translatedId ? (int) $translatedId : null;
	}

	public static function getElementType( int $id, array $field ) {
		return self::holdsTermIds( $field ) ? Obj::prop( 'taxonomy', $field ) : get_post_type( $id );
	}

	public static function addFallbackMarker( array $field, int $referencedId, string $marker ) {
		$metaType = self::holdsTermIds( $field ) ? ReferenceRepository::META_TYPE_TERM : ReferenceRepository::META_TYPE_POST;

		if ( ! in_array( $marker, (array) get_metadata( $metaType, $referencedId, FallbackReferenceHooks::MARKER_KEY, false ), true ) ) {
			add_metadata( $metaType, $referencedId, FallbackReferenceHooks::MARKER_KEY, $marker );
		}
	}

	public static function translateAll( $value, array $field, string $languageCode ) {
		if ( ! is_array( $value ) ) {
			return self::translateOne( $value, $field, $languageCode );
		}

		foreach ( $value as $index => $id ) {
			$value[ $index ] = self::translateOne( $id, $field, $languageCode );
		}

		return $value;
	}

	private static function translateOne( $id, array $field, string $languageCode ) {
		if ( ! is_numeric( $id ) ) {
			return $id;
		}

		$translatedId = self::getTranslatedId( (int) $id, $field, $languageCode );

		return $translatedId ? self::sameShape( $id, $translatedId ) : $id;
	}

	public static function replaceFallback( $value, $sourceValue, $referencedId, $translatedId ) {
		if ( ! is_array( $value ) ) {
			return self::isId( $value, $referencedId ) && self::isId( $sourceValue, $referencedId )
				? self::sameShape( $value, $translatedId )
				: $value;
		}

		if ( ! is_array( $sourceValue ) ) {
			return $value;
		}

		$isFallbackReplaced = false;

		foreach ( $value as $index => $id ) {
			if ( self::isId( $id, $referencedId ) && self::isId( $sourceValue[ $index ] ?? null, $referencedId ) ) {
				$value[ $index ]    = self::sameShape( $id, $translatedId );
				$isFallbackReplaced = true;
			}
		}

		return $isFallbackReplaced ? self::withoutRepeatsOf( $value, $translatedId ) : $value;
	}

	private static function withoutRepeatsOf( array $ids, $id ) {
		$firstIndex = array_search( (string) $id, array_map( 'strval', $ids ), true );

		return array_values(
			array_filter(
				$ids,
				function ( $listedId, $index ) use ( $id, $firstIndex ) {
					return $index === $firstIndex || ! self::isId( $listedId, $id );
				},
				ARRAY_FILTER_USE_BOTH
			)
		);
	}

	private static function isId( $value, $id ) {
		return is_scalar( $value ) && (string) $id === (string) $value;
	}

	private static function sameShape( $oldId, $newId ) {
		return is_int( $oldId ) ? $newId : (string) $newId;
	}
}
