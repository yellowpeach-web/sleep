<?php

namespace WPML\ST\DB\Mappers;

use \wpdb;

class StringsRetrieve {

	const CONTEXT_WORDPRESS = 'WordPress';
	const CONTEXT_DEFAULT   = 'default';

	const PAGE_SIZE = 1000;

	const CHANGED_FROM_MO_STRING = 'NOT ( CAST( st.value AS BINARY ) <=> CAST( st.mo_string AS BINARY ) )';

	private $wpdb;

	private $page_size;

	public function __construct( wpdb $wpdb, $page_size = self::PAGE_SIZE ) {
		$this->wpdb      = $wpdb;
		$this->page_size = max( 1, (int) $page_size );
	}

	public function get( $language, $domain, $modified_mo_only = false ) {
		$rows = $this->getDomainRows( $language, $domain, $modified_mo_only );

		if ( self::CONTEXT_DEFAULT === strtolower( (string) $domain ) ) {
			$rows = array_merge(
				$rows,
				array_values(
					array_filter(
						$this->getDomainRows( $language, self::CONTEXT_WORDPRESS, $modified_mo_only ),
						[ self::class, 'isWordPressCoreRow' ]
					)
				)
			);
		}

		return $rows;
	}

	public function getPluralFormRows( $language, $domain ) {
		return $this->withCoreCopies(
			$domain,
			function ( $context ) use ( $language ) {
				$wpdb = $this->wpdb;
				$ids  = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT id FROM {$wpdb->prefix}icl_strings WHERE context = %s AND value LIKE %s ORDER BY id",
						$context,
						'% [plural %]'
					)
				);
				$this->bailOnDbError();

				return $this->getRowsForIdList( is_array( $ids ) ? $ids : [], $language, $context );
			}
		);
	}

	public function getRowsByOriginals( $language, $domain, array $originals ) {
		$originals = array_values( array_unique( array_map( 'strval', $originals ) ) );
		if ( ! $originals ) {
			return [];
		}

		return $this->withCoreCopies(
			$domain,
			function ( $context ) use ( $language, $originals ) {
				$wpdb = $this->wpdb;
				$ids  = [];
				foreach ( array_chunk( $originals, max( 1, $this->page_size ) ) as $chunk ) {
					$values = array_merge( [ $context ], $chunk );
					$found  = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT id FROM {$wpdb->prefix}icl_strings WHERE context = %s AND value IN (" . implode( ',', array_fill( 0, count( $chunk ), '%s' ) ) . ') ORDER BY id',
							$values
						)
					);
					$this->bailOnDbError();
					$ids = array_merge( $ids, is_array( $found ) ? $found : [] );
				}

				return $this->getRowsForIdList( $ids, $language, $context );
			}
		);
	}

	private function getRowsForIdList( array $ids, $language, $domain ) {
		$rows = [];
		foreach ( array_chunk( array_values( array_unique( array_map( 'intval', $ids ) ) ), max( 1, $this->page_size ) ) as $page ) {
			$rows = array_merge( $rows, $this->getRowsForIds( $page, $language, $domain, false ) );
		}

		return $rows;
	}

	private function withCoreCopies( $domain, callable $fetch ) {
		$rows = $fetch( $domain );

		if ( self::CONTEXT_DEFAULT === strtolower( (string) $domain ) ) {
			$rows = array_merge(
				$rows,
				array_values( array_filter( $fetch( self::CONTEXT_WORDPRESS ), [ self::class, 'isWordPressCoreRow' ] ) )
			);
		}

		return $rows;
	}

	public static function isWordPressCoreRow( array $row ) {
		return isset( $row['name'], $row['original'] ) && md5( (string) $row['original'] ) === $row['name'];
	}

	private function getDomainRows( $language, $domain, $modified_mo_only ) {
		$rows   = [];
		$cursor = 0;

		do {
			$ids     = $this->getPageOfIds( $domain, $cursor );
			$fetched = count( $ids );

			if ( ! $fetched ) {
				break;
			}

			$rows   = array_merge( $rows, $this->getRowsForIds( $ids, $language, $domain, $modified_mo_only ) );
			$cursor = (int) end( $ids );
		} while ( $fetched === $this->page_size );

		return $rows;
	}

	private function getPageOfIds( $domain, $cursor ) {
		$ids = $this->wpdb->get_col( $this->wpdb->prepare( "SELECT id FROM {$this->wpdb->prefix}icl_strings WHERE context = %s AND id > %d ORDER BY id LIMIT %d", $domain, (int) $cursor, $this->page_size ) );
		$this->bailOnDbError();

		return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
	}

	private function getRowsForIds( array $ids, $language, $domain, $modified_mo_only ) {
		$query = "
			SELECT
				s.id,
				st.status,
				s.domain_name_context_md5 AS ctx ,
				st.value AS translated,
				st.mo_string AS mo_string,
				s.value AS original,
				s.gettext_context,
				s.name,
				s.context AS source_context
			FROM {$this->wpdb->prefix}icl_strings s
			LEFT JOIN {$this->wpdb->prefix}icl_string_translations AS st
				ON s.id = st.string_id
					AND st.language = %s
					AND s.language != %s
			WHERE s.context = %s
				AND s.id IN (" . wpml_prepare_in( $ids, '%d' ) . ')';

		if ( $modified_mo_only ) {
			$query .= $this->getModifiedMOOnlyWhere();
		}

		$query .= ' ORDER BY s.id';

		$rows = $this->getPreparedResults( $query, [ $language, $language, $domain ] );
		$this->bailOnDbError();

		return $rows;
	}

	public function getDomainLanguagesWithChanges( array $domains ) {
		if ( ! $domains ) {
			return [];
		}

		$query = "SELECT DISTINCT (BINARY s.context) as `domain`, st.language as `languageCode`
			FROM {$this->wpdb->prefix}icl_string_translations st
			INNER JOIN {$this->wpdb->prefix}icl_strings s ON s.id = st.string_id
			WHERE " . self::writtenTranslationCondition() . '
				AND s.context IN (' . implode( ', ', array_fill( 0, count( $domains ), '%s' ) ) . ')';

		return $this->getPreparedResults( $query, array_values( $domains ) );
	}

	private function getPreparedResults( $query, array $args ) {
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $query, ...$args ), ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	private function getModifiedMOOnlyWhere() {
		return ' AND ' . self::writtenTranslationCondition();
	}

	private static function writtenTranslationCondition() {
		$statuses = array_map( 'intval', [ ICL_TM_COMPLETE, ICL_TM_NEEDS_UPDATE ] );

		return 'st.status IN (' . implode( ', ', $statuses ) . ')' .
			' AND st.value IS NOT NULL' .
			' AND ' . self::CHANGED_FROM_MO_STRING;
	}

	private function bailOnDbError() {
		if ( isset( $this->wpdb->last_error ) && $this->wpdb->last_error ) {
			throw new \RuntimeException( 'icl_strings retrieval failed: ' . $this->wpdb->last_error );
		}
	}
}
