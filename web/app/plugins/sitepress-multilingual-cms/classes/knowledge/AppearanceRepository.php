<?php

namespace WPML\Knowledge;

use WPML\Upgrade\SchemaFacts;

class AppearanceRepository {

	const TABLE = 'icl_appearances';

	const CACHE_GROUP = 'wpml_appearances';

	const TTL = HOUR_IN_SECONDS;

	const SEEN_BY = [ 'admin', 'visitor' ];

	const SCHEMA_ERRNOS = [ 1146, 1054 ];

	const IMPORT_KIND_FRONTEND = 6;

	private $typeCollations = [];

	public function isAvailable() {
		return SchemaFacts::hasTable( self::TABLE );
	}

	public function findLastSeen( array $keys ) {
		if ( ! $this->isAvailable() ) {
			return null;
		}

		$pairs = [];
		foreach ( $keys as $key ) {
			$type = (string) $key[0];
			$id   = (int) $key[1];

			$pairs[ $type . ':' . $id ] = [ $type, $id ];
		}
		if ( ! $pairs ) {
			return [];
		}

		$wpdb = $this->wpdb();

		$rows = $wpdb->get_results(
			vsprintf(
				"SELECT element_type, element_id, last_seen FROM {$wpdb->prefix}icl_appearances WHERE (element_type, element_id) IN ("
				. implode( ', ', array_fill( 0, count( $pairs ), "('%s', %d)" ) )
				. ')',
				esc_sql( $this->flatten( $pairs ) )
			),
			ARRAY_A
		);

		if ( $this->failed() ) {
			return null;
		}

		$found = [];
		foreach ( (array) $rows as $row ) {
			$found[ $row['element_type'] . ':' . (int) $row['element_id'] ] = (string) $row['last_seen'];
		}

		return $found;
	}

	public function upsert( array $rows, $seenBy = null ) {
		if ( ! $this->isAvailable() ) {
			return null;
		}
		if ( ! $rows ) {
			return 0;
		}

		$seenBy = in_array( $seenBy, self::SEEN_BY, true ) ? $seenBy : '';

		$values = [];
		foreach ( $rows as $row ) {
			$values[] = [ (string) $row[0], (int) $row[1], (string) $row[2], (string) $row[3], $seenBy ];
		}

		$wpdb = $this->wpdb();

		$affected = $wpdb->query(
			vsprintf(
				"INSERT INTO {$wpdb->prefix}icl_appearances (element_type, element_id, first_seen, last_seen, seen_by) VALUES "
				. implode( ', ', array_fill( 0, count( $values ), "('%s', %d, '%s', '%s', NULLIF('%s', ''))" ) )
				. ' ON DUPLICATE KEY UPDATE last_seen = GREATEST(last_seen, VALUES(last_seen))',
				esc_sql( $this->flatten( $values ) )
			)
		);

		if ( $this->failed() || false === $affected ) {
			return null;
		}

		return (int) $affected;
	}

	public function has( $type, $id ) {
		if ( ! $this->isAvailable() ) {
			return null;
		}

		$wpdb = $this->wpdb();

		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->prefix}icl_appearances WHERE element_type = %s AND element_id = %d LIMIT 1",
				(string) $type,
				(int) $id
			)
		);

		if ( $this->failed() ) {
			return null;
		}

		return null !== $found;
	}

	public function filterIds( $type, array $ids ) {
		if ( ! $this->isAvailable() ) {
			return null;
		}

		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $ids ),
					function ( $id ) {
						return $id > 0;
					}
				)
			)
		);
		if ( ! $ids ) {
			return [];
		}

		$wpdb = $this->wpdb();

		$found = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT element_id FROM {$wpdb->prefix}icl_appearances WHERE element_type = %s AND element_id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
				array_merge( [ (string) $type ], $ids )
			)
		);

		if ( $this->failed() ) {
			return null;
		}

		$found = array_map( 'intval', (array) $found );

		return array_values( array_intersect( $ids, $found ) );
	}

	public function countMissing( $type ) {
		if ( ! $this->isAvailable() ) {
			return null;
		}
		if ( 'string' === (string) $type && ! SchemaFacts::hasTable( 'icl_strings' ) ) {
			return null;
		}

		$wpdb = $this->wpdb();

		if ( 'string' === (string) $type ) {
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}icl_strings strings LEFT JOIN {$wpdb->prefix}icl_appearances ap ON ap.element_type = %s AND ap.element_id = strings.id WHERE strings.string_type = 1 AND ap.element_id IS NULL",
					'string'
				)
			);
		} else {
			$collation = $this->typeCollation();
			if ( null === $collation ) {
				return null;
			}

			if ( $collation ) {
				$count = $wpdb->get_var(
					vsprintf(
						"SELECT COUNT(*) FROM {$wpdb->prefix}icl_translations t LEFT JOIN {$wpdb->prefix}icl_appearances ap ON ap.element_type = CONVERT( t.element_type USING %s ) COLLATE %s AND ap.element_id = t.element_id WHERE t.element_type = '%s' AND t.source_language_code IS NULL AND ap.element_id IS NULL",
						esc_sql( [ $collation[0], $collation[1], (string) $type ] )
					)
				);
			} else {
				$count = $wpdb->get_var(
					vsprintf(
						"SELECT COUNT(*) FROM {$wpdb->prefix}icl_translations t LEFT JOIN {$wpdb->prefix}icl_appearances ap ON ap.element_type = t.element_type AND ap.element_id = t.element_id WHERE t.element_type = '%s' AND t.source_language_code IS NULL AND ap.element_id IS NULL",
						esc_sql( [ (string) $type ] )
					)
				);
			}
		}

		if ( $this->failed() || null === $count ) {
			return null;
		}

		return (int) $count;
	}

	public function existsSql( $typeExpr, $idExpr ) {
		if ( ! $this->isAvailable() ) {
			return '';
		}

		$collation = $this->typeCollation();
		if ( null === $collation ) {
			return '';
		}

		$wpdb = $this->wpdb();
		$type = $collation
			? "CONVERT( {$typeExpr} USING {$collation[0]} ) COLLATE {$collation[1]}"
			: $typeExpr;

		return "EXISTS ( SELECT 1 FROM {$wpdb->prefix}icl_appearances wpml_appearances WHERE wpml_appearances.element_type = {$type} AND wpml_appearances.element_id = {$idExpr} )";
	}

	public function deleteByType( $type = null ) {
		if ( ! $this->isAvailable() ) {
			return null;
		}

		$wpdb = $this->wpdb();

		if ( null === $type ) {
			$deleted = $wpdb->query( "DELETE FROM {$wpdb->prefix}icl_appearances" );
		} else {
			$deleted = $wpdb->query(
				$wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_appearances WHERE element_type = %s", (string) $type )
			);
		}

		if ( $this->failed() || false === $deleted ) {
			return null;
		}

		return (int) $deleted;
	}

	public function delete( $type, $id ) {
		if ( ! $this->isAvailable() ) {
			return null;
		}

		$wpdb = $this->wpdb();

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}icl_appearances WHERE element_type = %s AND element_id = %d",
				(string) $type,
				(int) $id
			)
		);

		if ( $this->failed() || false === $deleted ) {
			return null;
		}

		return (int) $deleted;
	}

	public function stats() {
		if ( ! $this->isAvailable() ) {
			return null;
		}

		$wpdb = $this->wpdb();

		$rows = $wpdb->get_results(
			"SELECT element_type, COUNT(*) AS total, MIN(last_seen) AS oldest, MAX(last_seen) AS newest FROM {$wpdb->prefix}icl_appearances GROUP BY element_type ORDER BY element_type",
			ARRAY_A
		);

		if ( $this->failed() ) {
			return null;
		}

		$stats = [];
		foreach ( (array) $rows as $row ) {
			$stats[ (string) $row['element_type'] ] = [
				'rows'   => (int) $row['total'],
				'oldest' => (string) $row['oldest'],
				'newest' => (string) $row['newest'],
			];
		}

		return $stats;
	}

	public function importStringsFromPositions() {
		if ( ! $this->isAvailable() || ! SchemaFacts::hasTable( 'icl_string_positions' ) || ! SchemaFacts::hasTable( 'icl_strings' ) ) {
			return null;
		}

		$wpdb = $this->wpdb();

		$copied = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->prefix}icl_appearances (element_type, element_id, first_seen, last_seen, seen_by) SELECT DISTINCT %s, positions.string_id, UTC_TIMESTAMP(), UTC_TIMESTAMP(), NULL FROM {$wpdb->prefix}icl_string_positions positions INNER JOIN {$wpdb->prefix}icl_strings strings ON strings.id = positions.string_id WHERE positions.kind = %d",
				'string',
				self::IMPORT_KIND_FRONTEND
			)
		);

		if ( $this->failed() || false === $copied ) {
			return null;
		}

		return (int) $copied;
	}

	private function flatten( array $rows ) {
		$flat = [];
		foreach ( $rows as $row ) {
			foreach ( $row as $value ) {
				$flat[] = $value;
			}
		}

		return $flat;
	}

	private function typeCollation() {
		$wpdb   = $this->wpdb();
		$prefix = (string) $wpdb->prefix;

		if ( ! array_key_exists( $prefix, $this->typeCollations ) ) {
			$column = $wpdb->get_row(
				$wpdb->prepare( "SHOW FULL COLUMNS FROM `{$wpdb->prefix}icl_appearances` LIKE %s", 'element_type' ),
				ARRAY_A
			);
			if ( $this->failed() ) {
				return null;
			}

			$collation = is_array( $column ) && isset( $column['Collation'] ) ? (string) $column['Collation'] : '';

			$this->typeCollations[ $prefix ] = preg_match( '/^([A-Za-z0-9]+)_[A-Za-z0-9_]+$/', $collation, $match )
				? [ $match[1], $collation ]
				: [];
		}

		return $this->typeCollations[ $prefix ];
	}

	private function wpdb() {
		return $GLOBALS['wpdb'];
	}

	private function failed() {
		$wpdb = $this->wpdb();

		if ( ! isset( $wpdb->last_error ) || '' === (string) $wpdb->last_error ) {
			return false;
		}

		if ( $this->isSchemaError( $wpdb ) ) {
			SchemaFacts::forgetIfQueryFailed( $wpdb );
		}

		return true;
	}

	private function isSchemaError( $wpdb ) {
		$errno = $this->errno( $wpdb );
		if ( null !== $errno ) {
			return in_array( $errno, self::SCHEMA_ERRNOS, true );
		}

		return 1 === preg_match( "/^(?:Table '[^']+' doesn't exist|Unknown column '[^']+' in '[^']+')$/", (string) $wpdb->last_error );
	}

	private function errno( $wpdb ) {
		$dbh = isset( $wpdb->dbh ) ? $wpdb->dbh : null;
		if ( ! $dbh instanceof \mysqli ) {
			return null;
		}

		try {
			return (int) $dbh->errno;
		} catch ( \Throwable $closed ) {
			return null;
		}
	}
}
