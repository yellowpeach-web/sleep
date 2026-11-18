<?php

namespace WPML\ST\TranslationFile\Sync;

use WPML\Collect\Support\Collection;
use WPML\ST\DB\Mappers\StringsRetrieve as DbStringsRetrieve;
use WPML\ST\TranslationFile\FileMark;
use WPML\ST\TranslationFile\StringCollation;

class TranslationUpdates {
	use StringCollation;

	const ICL_STRING_TRANSLATION_COMPLETE     = 10;
	const ICL_STRING_TRANSLATION_NEEDS_UPDATE = 3;

	private $wpdb;

	private $languageRecords;

	private $data;

	public function __construct( \wpdb $wpdb, \WPML_Language_Records $languageRecords ) {
		$this->wpdb            = $wpdb;
		$this->languageRecords = $languageRecords;
	}

	public function getTimestamp( $domain, $locale ) {
		$this->loadData();
		$lang = $this->languageRecords->get_language_code( $locale );
		return (int) $this->data->get( "$lang#$domain" );
	}

	private function loadData() {
		if ( ! $this->data ) {
			$wpdb = $this->wpdb;

			$this->data = wpml_collect(
				$wpdb->get_results(
					$wpdb->prepare(
						"SELECT
							CONCAT(st.language,'#',s.context " . esc_sql( $this->getCollateForContextColumn( $wpdb ) ) . ") AS lang_domain,
							UNIX_TIMESTAMP(MAX(st.translation_date)) as last_update
						FROM {$wpdb->prefix}icl_string_translations AS st
						INNER JOIN {$wpdb->prefix}icl_strings AS s ON st.string_id = s.id
						WHERE st.value IS NOT NULL AND st.status = %d
						GROUP BY lang_domain",
						self::ICL_STRING_TRANSLATION_COMPLETE
					)
				)
			)->pluck( 'last_update', 'lang_domain' );
		}
	}

	public function getMarks( array $domains, $locale ) {
		$domains = array_values( array_unique( array_filter( array_map( 'strval', $domains ), 'strlen' ) ) );
		$lang    = $this->languageRecords->get_language_code( $locale );

		if ( ! $domains || ! $lang ) {
			return [];
		}

		$wpdb = $this->wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.context AS domain, UNIX_TIMESTAMP(MAX(st.translation_date)) AS newest, COUNT(*) AS served
				FROM {$wpdb->prefix}icl_string_translations AS st
				INNER JOIN {$wpdb->prefix}icl_strings AS s ON st.string_id = s.id
				WHERE st.language = %s
					AND st.status IN (%d, %d)
					AND st.value IS NOT NULL
					AND ( CAST( st.value AS BINARY ) NOT IN ('', '0') OR CAST( st.mo_string AS BINARY ) NOT IN ('', '0') )
					AND ( CAST( LOWER( s.context ) AS BINARY ) NOT IN ( 'default', 'wordpress' ) OR NOT ( CAST( st.value AS BINARY ) <=> CAST( st.mo_string AS BINARY ) ) )
					AND s.language <> st.language
					AND s.context IN (" . implode( ', ', array_fill( 0, count( $domains ), '%s' ) ) . ')
				GROUP BY s.context',
				array_merge( [ $lang, self::ICL_STRING_TRANSLATION_COMPLETE, self::ICL_STRING_TRANSLATION_NEEDS_UPDATE ], $domains )
			),
			ARRAY_A
		);

		$found = [];
		foreach ( (array) $rows as $row ) {
			$found[ strtolower( (string) $row['domain'] ) ] = [ (int) $row['newest'], (int) $row['served'] ];
		}

		$default = DbStringsRetrieve::CONTEXT_DEFAULT;
		if ( in_array( $default, array_map( 'strtolower', $domains ), true ) ) {
			foreach ( $this->getWordPressCoreRows( $lang ) as $row ) {
				$mark              = isset( $found[ $default ] ) ? $found[ $default ] : [ 0, 0 ];
				$found[ $default ] = [ max( $mark[0], (int) $row['newest'] ), $mark[1] + 1 ];
			}
		}

		$marks = [];
		foreach ( $domains as $domain ) {
			$key = strtolower( $domain );
			if ( isset( $found[ $key ] ) ) {
				$marks[ $domain ] = FileMark::format( $found[ $key ][0], $found[ $key ][1] );
			}
		}

		return $marks;
	}

	private function getWordPressCoreRows( $lang ) {
		$wpdb = $this->wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.value AS original, s.name, UNIX_TIMESTAMP(st.translation_date) AS newest
				FROM {$wpdb->prefix}icl_string_translations AS st
				INNER JOIN {$wpdb->prefix}icl_strings AS s ON st.string_id = s.id
				WHERE st.language = %s
					AND st.status IN (%d, %d)
					AND st.value IS NOT NULL
					AND ( CAST( st.value AS BINARY ) NOT IN ('', '0') OR CAST( st.mo_string AS BINARY ) NOT IN ('', '0') )
					AND NOT ( CAST( st.value AS BINARY ) <=> CAST( st.mo_string AS BINARY ) )
					AND s.language <> st.language
					AND s.context = %s",
				[ $lang, self::ICL_STRING_TRANSLATION_COMPLETE, self::ICL_STRING_TRANSLATION_NEEDS_UPDATE, DbStringsRetrieve::CONTEXT_WORDPRESS ]
			),
			ARRAY_A
		);

		return array_filter( (array) $rows, [ DbStringsRetrieve::class, 'isWordPressCoreRow' ] );
	}

	public function reset() {
		$this->data = null;
	}
}
