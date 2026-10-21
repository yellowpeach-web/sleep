<?php

namespace WPML\Troubleshooting;

class OrphanLanguageCodes {

	const CODE_MAX_LENGTH = 7;

	private static $codes = null;

	private static $report = null;

	private static $hasAny = null;

	private static $hasEmptyCode = null;

	private static $tables = array();

	private static $scanError = '';

	public static function resetCache() {
		self::$codes        = null;
		self::$report       = null;
		self::$hasAny       = null;
		self::$hasEmptyCode = null;
		self::$tables       = array();
		self::$scanError    = '';
	}

	public static function lastScanError() {
		return '' === self::$scanError ? null : self::$scanError;
	}

	public static function codes() {
		global $wpdb;

		if ( null !== self::$codes ) {
			return self::$codes;
		}

		$codes = (array) $wpdb->get_col(
			"SELECT DISTINCT t.language_code AS code
			   FROM {$wpdb->prefix}icl_translations t
			   LEFT JOIN {$wpdb->prefix}icl_languages l ON l.code = t.language_code
			  WHERE l.code IS NULL AND t.language_code IS NOT NULL AND t.language_code <> ''
			 UNION
			 SELECT DISTINCT t.source_language_code
			   FROM {$wpdb->prefix}icl_translations t
			   LEFT JOIN {$wpdb->prefix}icl_languages l ON l.code = t.source_language_code
			  WHERE t.source_language_code IS NOT NULL AND t.source_language_code <> ''
			    AND l.code IS NULL"
		);

		self::captureScanError();

		self::$codes = array_values( array_filter( array_map( 'strval', $codes ), 'strlen' ) );

		return self::$codes;
	}

	public static function hasAny() {
		global $wpdb;

		if ( null !== self::$hasAny ) {
			return self::$hasAny;
		}

		$found = $wpdb->get_var(
			"SELECT 1
			   FROM {$wpdb->prefix}icl_translations t
			   LEFT JOIN {$wpdb->prefix}icl_languages l ON l.code = t.language_code
			   LEFT JOIN {$wpdb->prefix}icl_languages s ON s.code = t.source_language_code
			  WHERE ( l.code IS NULL AND t.language_code IS NOT NULL AND t.language_code <> '' )
			     OR ( s.code IS NULL AND t.source_language_code IS NOT NULL AND t.source_language_code <> '' )
			  LIMIT 1"
		);

		$failed = self::captureScanError();

		self::$hasAny = ! $failed && (bool) $found;

		return self::$hasAny;
	}

	public static function hasEmptyCode() {
		global $wpdb;

		if ( null !== self::$hasEmptyCode ) {
			return self::$hasEmptyCode;
		}

		$found = $wpdb->get_var(
			"SELECT 1
			   FROM {$wpdb->prefix}icl_translations t
			  WHERE t.language_code = ''
			     OR ( t.source_language_code IS NOT NULL AND t.source_language_code = '' )
			  LIMIT 1"
		);

		$failed = self::captureScanError();

		self::$hasEmptyCode = ! $failed && (bool) $found;

		return self::$hasEmptyCode;
	}

	public static function report() {
		if ( null !== self::$report ) {
			return self::$report;
		}

		$report = array();
		$seen   = array();

		foreach ( array_merge( self::codes(), self::stringCodes() ) as $code ) {
			$key = strtolower( $code );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$row = self::reportFor( $code );
			if ( $row ) {
				$report[]     = $row;
				$seen[ $key ] = true;
			}
		}

		self::$report = $report;

		return self::$report;
	}

	public static function reportFor( $code ) {
		$code = (string) $code;

		if ( '' === $code || self::isDefined( $code ) ) {
			return null;
		}

		$types = self::contentTypes( $code );
		$total = 0;
		foreach ( $types as $type ) {
			$total += $type['count'];
		}

		$sourceOnly = $total < 1 && self::isSourceLanguage( $code );
		$strings    = self::stringCount( $code );

		if ( $total < 1 && ! $sourceOnly && $strings < 1 ) {
			return null;
		}

		return array(
			'code'          => $code,
			'types'         => $types,
			'total'         => $total,
			'sourceOnly'    => $sourceOnly,
			'strings'       => $strings,
			'stringOnly'    => $total < 1 && ! $sourceOnly,
			'synthesizable' => strlen( $code ) <= self::CODE_MAX_LENGTH,
		);
	}

	private static function captureScanError() {
		global $wpdb;

		$error = isset( $wpdb->last_error ) ? (string) $wpdb->last_error : '';

		if ( '' === $error ) {
			return false;
		}

		if ( '' === self::$scanError ) {
			self::$scanError = $error;
		}

		return true;
	}

	private static function isDefined( $code ) {
		global $wpdb;

		$found = $wpdb->get_var(
			$wpdb->prepare( "SELECT code FROM {$wpdb->prefix}icl_languages WHERE code = %s", $code )
		);

		return null !== $found;
	}

	private static function contentTypes( $code ) {
		global $wpdb;

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT element_type, COUNT(*) AS c
				   FROM {$wpdb->prefix}icl_translations
				  WHERE language_code = %s
			   GROUP BY element_type",
				$code
			)
		);

		self::captureScanError();

		$types = array();
		foreach ( $rows as $row ) {
			$count = (int) $row->c;
			if ( $count < 1 ) {
				continue;
			}
			$types[] = self::describe( (string) $row->element_type, $count );
		}

		return $types;
	}

	private static function describe( $elementType, $count ) {
		if ( 0 === strpos( $elementType, 'post_' ) ) {
			$slug = substr( $elementType, strlen( 'post_' ) );

			return array(
				'kind'  => 'post',
				'slug'  => $slug,
				'label' => self::labelFor( get_post_type_object( $slug ), $slug, $count ),
				'count' => $count,
			);
		}

		if ( 0 === strpos( $elementType, 'tax_' ) ) {
			$slug = substr( $elementType, strlen( 'tax_' ) );

			return array(
				'kind'  => 'taxonomy',
				'slug'  => $slug,
				'label' => self::labelFor( get_taxonomy( $slug ), $slug, $count ),
				'count' => $count,
			);
		}

		return array(
			'kind'  => 'other',
			'slug'  => $elementType,
			'label' => $elementType,
			'count' => $count,
		);
	}

	private static function labelFor( $object, $fallback, $count ) {
		if ( 1 === (int) $count && $object && ! empty( $object->labels->singular_name ) ) {
			return (string) $object->labels->singular_name;
		}
		if ( $object && ! empty( $object->labels->name ) ) {
			return (string) $object->labels->name;
		}

		return $fallback;
	}

	private static function isSourceLanguage( $code ) {
		global $wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->prefix}icl_translations WHERE source_language_code = %s LIMIT 1",
				$code
			)
		);
	}

	private static function stringCount( $code ) {
		global $wpdb;

		if ( ! self::stringTablesExist() ) {
			return 0;
		}

		$sources = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}icl_strings WHERE language = %s", $code )
		);
		$translated = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}icl_string_translations WHERE language = %s", $code )
		);

		return $sources + $translated;
	}

	private static function stringCodes() {
		global $wpdb;

		if ( ! self::stringTablesExist() ) {
			return array();
		}

		$codes = (array) $wpdb->get_col(
			"SELECT DISTINCT s.language AS code
			   FROM {$wpdb->prefix}icl_strings s
			   LEFT JOIN {$wpdb->prefix}icl_languages l ON l.code = s.language
			  WHERE l.code IS NULL AND s.language IS NOT NULL AND s.language <> ''
			 UNION
			 SELECT DISTINCT st.language
			   FROM {$wpdb->prefix}icl_string_translations st
			   LEFT JOIN {$wpdb->prefix}icl_languages l ON l.code = st.language
			  WHERE l.code IS NULL AND st.language IS NOT NULL AND st.language <> ''"
		);

		self::captureScanError();

		return array_values( array_filter( array_map( 'strval', $codes ), 'strlen' ) );
	}

	private static function stringTablesExist() {
		global $wpdb;

		return self::tableExists( $wpdb->prefix . 'icl_strings' )
			&& self::tableExists( $wpdb->prefix . 'icl_string_translations' );
	}

	private static function tableExists( $table ) {
		global $wpdb;

		if ( ! isset( self::$tables[ $table ] ) ) {
			self::$tables[ $table ] = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		}

		return self::$tables[ $table ];
	}
}
