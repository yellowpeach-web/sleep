<?php

namespace ACFML\Repeater\Sync;

class StaleJobs {

	const META_KEY = '_acfml_rows_rekeyed';

	public static function mark( $elementType, $elementId, array $languageCodes ) {
		if ( ! $languageCodes ) {
			return;
		}

		$marked = array_values( array_unique( array_merge( self::read( $elementType, $elementId ), $languageCodes ) ) );

		self::write( $elementType, $elementId, $marked );
	}

	public static function isMarked( $elementType, $elementId, $languageCode ) {
		return in_array( $languageCode, self::read( $elementType, $elementId ), true );
	}

	public static function clear( $elementType, $elementId, $languageCode ) {
		$marked = self::read( $elementType, $elementId );
		$left   = array_values( array_diff( $marked, [ $languageCode ] ) );

		if ( count( $left ) !== count( $marked ) ) {
			self::write( $elementType, $elementId, $left );
		}
	}

	private static function read( $elementType, $elementId ) {
		if ( ! $elementId ) {
			return [];
		}

		$stored = self::isTerm( $elementType )
			? get_term_meta( (int) $elementId, self::META_KEY, true )
			: get_post_meta( (int) $elementId, self::META_KEY, true );

		return is_array( $stored ) ? $stored : [];
	}

	private static function write( $elementType, $elementId, array $languageCodes ) {
		if ( ! $elementId ) {
			return;
		}

		if ( self::isTerm( $elementType ) ) {
			if ( $languageCodes ) {
				update_term_meta( (int) $elementId, self::META_KEY, $languageCodes );
			} else {
				delete_term_meta( (int) $elementId, self::META_KEY );
			}

			return;
		}

		if ( $languageCodes ) {
			update_post_meta( (int) $elementId, self::META_KEY, $languageCodes );
		} else {
			delete_post_meta( (int) $elementId, self::META_KEY );
		}
	}

	private static function isTerm( $elementType ) {
		return 0 === strpos( (string) $elementType, 'tax' );
	}
}
