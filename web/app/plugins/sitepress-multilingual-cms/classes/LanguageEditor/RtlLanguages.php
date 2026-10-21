<?php

namespace WPML\LanguageEditor;

class RtlLanguages {

	const DEFAULT_RTL_LANGUAGES = [ 'ar', 'he', 'fa', 'ku', 'ur' ];

	private static $storedOverrides;

	public static function isRtl( $code ) {
		$code = strtolower( trim( (string) $code ) );
		if ( '' === $code ) {
			return false;
		}

		return (bool) apply_filters( 'wpml_language_is_rtl', self::resolve( $code ), $code );
	}

	private static function resolve( $code ) {

		$override = self::storedOverride( $code );
		if ( null !== $override ) {
			return $override;
		}

		$rtlCodes = apply_filters( 'wpml_rtl_languages_codes', self::DEFAULT_RTL_LANGUAGES );
		$rtlCodes = is_array( $rtlCodes ) ? array_map( 'strtolower', array_map( 'strval', $rtlCodes ) ) : [];

		$resolved = LanguageCodeResolution::resolve( $code );
		$language = self::languageOf( $code, $resolved );

		$added = array_values( array_diff( $rtlCodes, self::DEFAULT_RTL_LANGUAGES ) );
		if ( self::matches( $code, $language, $added ) ) {
			return true;
		}

		if ( null !== $resolved && null !== $resolved['rtl'] ) {
			return $resolved['rtl'];
		}

		return self::matches( $code, $language, $rtlCodes );
	}

	private static function matches( $code, $language, array $set ) {
		if ( in_array( $code, $set, true ) ) {
			return true;
		}

		return null !== $language && in_array( $language, $set, true );
	}

	private static function languageOf( $code, $resolved ) {
		if ( null !== $resolved ) {
			return strtolower( (string) $resolved['language'] );
		}

		$head = LanguageCodeResolution::head( $code );
		if ( $head === $code ) {
			return null;
		}

		$headResolved = LanguageCodeResolution::resolve( $head );

		return strtolower( null !== $headResolved ? (string) $headResolved['language'] : $head );
	}

	private static function storedOverride( $code ) {
		if ( null === self::$storedOverrides ) {
			self::$storedOverrides = self::readStoredOverrides();
		}

		return array_key_exists( $code, self::$storedOverrides ) ? self::$storedOverrides[ $code ] : null;
	}

	private static function readStoredOverrides() {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) ) {
			return [];
		}

		if ( ! \WPML\Upgrade\SchemaFacts::hasColumn( 'icl_languages', 'is_rtl' ) ) {
			return [];
		}

		$map  = [];
		$rows = $wpdb->get_results(
			"SELECT code, is_rtl FROM {$wpdb->prefix}icl_languages WHERE is_rtl IS NOT NULL",
			ARRAY_A
		);
		\WPML\Upgrade\SchemaFacts::forgetIfQueryFailed( $wpdb );
		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['code'] ) ) {
				continue;
			}
			$map[ strtolower( (string) $row['code'] ) ] = (bool) (int) $row['is_rtl'];
		}

		return $map;
	}

	public static function resetStoredOverrides() {
		self::$storedOverrides = null;
		\WPML\Upgrade\SchemaFacts::forget();
	}
}
