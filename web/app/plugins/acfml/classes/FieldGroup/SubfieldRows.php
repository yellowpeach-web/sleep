<?php

namespace ACFML\FieldGroup;

use ACFML\FieldPreferences\SubfieldRules;
use ACFML\Helper\Fields;
use WPML\FP\Fns;

class SubfieldRows {

	const TYPE_POST = 'post';
	const TYPE_TERM = 'term';

	const CHUNK_SIZE = 500;

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public static function patternsForFieldNames( array $fieldNames, $type ) {
		$wanted = array_flip( self::cleanNames( $fieldNames ) );

		if ( ! $wanted ) {
			return [];
		}

		$patterns = [];

		$collect = function ( $field, $fieldPattern ) use ( $wanted, &$patterns ) {
			$name = self::sourceName( $field );

			if ( isset( $wanted[ $name ] ) && preg_quote( $name, '#' ) !== $fieldPattern ) {
				$patterns[] = $fieldPattern;
			}

			return $field;
		};

		foreach ( (array) acf_get_field_groups() as $fieldGroup ) {
			if ( in_array( $type, SubfieldRules::elementTypesForGroup( $fieldGroup ), true ) ) {
				Fields::iterate( (array) acf_get_fields( $fieldGroup ), $collect, Fns::identity() );
			}
		}

		return array_values( array_unique( $patterns ) );
	}

	public function postIdsForFieldNames( array $fieldNames ) {
		$fieldNames = self::cleanNames( $fieldNames );
		$patterns   = self::patternsForFieldNames( $fieldNames, self::TYPE_POST );

		if ( ! $patterns ) {
			return [];
		}

		$translations = $this->wpdb->prefix . 'icl_translations';

		$candidateIds = $this->wpdb->get_col(
			"SELECT DISTINCT pm.post_id
			FROM {$this->wpdb->postmeta} pm
			INNER JOIN {$translations} orig
				ON orig.element_id = pm.post_id
				AND orig.element_type LIKE 'post_%'
				AND orig.source_language_code IS NULL
			WHERE " . $this->likePreFilterClause( 'pm.meta_key', $patterns ) . "
				AND NOT EXISTS (
					SELECT 1 FROM {$this->wpdb->postmeta} ex
					WHERE ex.post_id = pm.post_id
						AND ex.meta_key IN (" . wpml_prepare_in( $fieldNames ) . ")
				)
				AND EXISTS (
					SELECT 1 FROM {$translations} tr
					WHERE tr.trid = orig.trid
						AND tr.source_language_code IS NOT NULL
				)"
		);

		return $this->idsWithLiveRows( (array) $candidateIds, $patterns, $this->wpdb->postmeta, 'post_id' );
	}

	public function termIdsForFieldNames( array $fieldNames ) {
		$fieldNames = self::cleanNames( $fieldNames );
		$patterns   = self::patternsForFieldNames( $fieldNames, self::TYPE_TERM );

		if ( ! $patterns ) {
			return [];
		}

		$translations = $this->wpdb->prefix . 'icl_translations';

		$candidateIds = $this->wpdb->get_col(
			"SELECT DISTINCT tm.term_id
			FROM {$this->wpdb->termmeta} tm
			INNER JOIN {$this->wpdb->term_taxonomy} tt
				ON tt.term_id = tm.term_id
			INNER JOIN {$translations} orig
				ON orig.element_id = tt.term_taxonomy_id
				AND orig.element_type LIKE 'tax_%'
				AND orig.source_language_code IS NULL
			WHERE " . $this->likePreFilterClause( 'tm.meta_key', $patterns ) . "
				AND NOT EXISTS (
					SELECT 1 FROM {$this->wpdb->termmeta} ex
					WHERE ex.term_id = tm.term_id
						AND ex.meta_key IN (" . wpml_prepare_in( $fieldNames ) . ")
				)
				AND EXISTS (
					SELECT 1 FROM {$translations} tr
					WHERE tr.trid = orig.trid
						AND tr.source_language_code IS NOT NULL
				)"
		);

		return $this->idsWithLiveRows( (array) $candidateIds, $patterns, $this->wpdb->termmeta, 'term_id' );
	}

	public function rowKeysWithin( array $fieldNames, array $ids, $type = self::TYPE_POST ) {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );

		if ( ! $ids ) {
			return [];
		}

		$patterns = self::patternsForFieldNames( $fieldNames, $type );

		if ( ! $patterns ) {
			return [];
		}

		list( $table, $idColumn ) = self::TYPE_TERM === $type
			? [ $this->wpdb->termmeta, 'term_id' ]
			: [ $this->wpdb->postmeta, 'post_id' ];

		$rows = $this->rowsWithin( $ids, $patterns, $table, $idColumn );

		return $this->liveRows( $rows, $patterns, $table, $idColumn );
	}

	public static function withReferences( string $metaType, int $id, array $metaKeys ) {
		$withReferences = [];

		foreach ( $metaKeys as $metaKey ) {
			$withReferences[] = $metaKey;

			if ( metadata_exists( $metaType, $id, '_' . $metaKey ) ) {
				$withReferences[] = '_' . $metaKey;
			}
		}

		return $withReferences;
	}

	public static function namesByNewPreference( $changes ) {
		$byPreference = [];

		foreach ( (array) $changes as $name => $change ) {
			$name = (string) $name;

			if ( '' === $name || ! is_array( $change ) || ! isset( $change['newPref'] ) ) {
				continue;
			}

			$preference = (int) $change['newPref'];

			if ( in_array( $preference, [ WPML_TRANSLATE_CUSTOM_FIELD, WPML_COPY_CUSTOM_FIELD, WPML_COPY_ONCE_CUSTOM_FIELD ], true ) ) {
				$byPreference[ $preference ][] = $name;
			}
		}

		return $byPreference;
	}

	private function idsWithLiveRows( array $ids, array $patterns, string $table, string $idColumn ) {
		$liveIds = [];

		foreach ( array_chunk( array_map( 'intval', $ids ), self::CHUNK_SIZE ) as $chunk ) {
			$rows    = $this->rowsWithin( $chunk, $patterns, $table, $idColumn );
			$liveIds = array_merge( $liveIds, array_keys( $this->liveRows( $rows, $patterns, $table, $idColumn ) ) );
		}

		sort( $liveIds );

		return $liveIds;
	}

	private function rowsWithin( array $ids, array $patterns, string $table, string $idColumn ) {
		$rows = $this->wpdb->get_results(
			"SELECT {$idColumn} AS id, meta_key
			FROM {$table}
			WHERE {$idColumn} IN (" . implode( ',', array_map( 'intval', $ids ) ) . ')
				AND ' . $this->likePreFilterClause( 'meta_key', $patterns )
		);

		return (array) $rows;
	}

	private function liveRows( array $rows, array $patterns, string $table, string $idColumn ) {
		$candidates = [];
		$containers = [];

		foreach ( $rows as $row ) {
			$id      = (int) $row->id;
			$metaKey = (string) $row->meta_key;

			foreach ( $patterns as $pattern ) {
				$levels = self::containerAndRowIndexPerLevel( $pattern, $metaKey );

				if ( null === $levels ) {
					continue;
				}

				$candidates[ $id ][ $metaKey ] = $levels;

				foreach ( $levels as $level ) {
					$containers[ $level['container'] ] = true;
				}

				break;
			}
		}

		$rowCounts = $containers ? $this->rowCounts( array_keys( $candidates ), array_keys( $containers ), $table, $idColumn ) : [];
		$live      = [];

		foreach ( $candidates as $id => $metaKeys ) {
			$counts = isset( $rowCounts[ $id ] ) ? $rowCounts[ $id ] : [];

			foreach ( $metaKeys as $metaKey => $levels ) {
				if ( self::isLive( $levels, $counts ) ) {
					$live[ $id ][] = (string) $metaKey;
				}
			}
		}

		ksort( $live );

		return $live;
	}

	private static function containerAndRowIndexPerLevel( string $pattern, string $metaKey ) {
		$regex = '#^' . str_replace( '\d+', '(\d+)', $pattern ) . '$#';

		if ( ! preg_match( $regex, $metaKey, $matches, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}

		$levels = [];

		foreach ( array_slice( $matches, 1 ) as $match ) {
			$levels[] = [
				'container' => substr( $metaKey, 0, $match[1] - 1 ),
				'index'     => (int) $match[0],
			];
		}

		return $levels;
	}

	private static function isLive( array $levels, array $counts ) {
		foreach ( $levels as $level ) {
			if ( ! isset( $counts[ $level['container'] ] ) || $level['index'] >= $counts[ $level['container'] ] ) {
				return false;
			}
		}

		return true;
	}

	private function rowCounts( array $ids, array $containerKeys, string $table, string $idColumn ) {
		$keysIn = wpml_prepare_in( $containerKeys );
		$counts = [];

		foreach ( array_chunk( $ids, self::CHUNK_SIZE ) as $chunk ) {
			$rows = $this->wpdb->get_results(
				"SELECT {$idColumn} AS id, meta_key, meta_value
				FROM {$table}
				WHERE {$idColumn} IN (" . implode( ',', array_map( 'intval', $chunk ) ) . ")
					AND meta_key IN ({$keysIn})"
			);

			foreach ( (array) $rows as $row ) {
				$counts[ (int) $row->id ][ (string) $row->meta_key ] = self::rowCount( $row->meta_value );
			}
		}

		return $counts;
	}

	private static function rowCount( $value ) {
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}

		$value = maybe_unserialize( $value );

		return is_array( $value ) ? count( $value ) : 0;
	}

	private function likePreFilterClause( string $column, array $patterns ) {
		$clauses = [];

		foreach ( $patterns as $pattern ) {
			$parts = array_map(
				function ( $part ) {
					return $this->wpdb->esc_like( stripslashes( $part ) );
				},
				explode( '\d+', $pattern )
			);

			$clauses[] = $column . $this->wpdb->prepare( ' LIKE %s', implode( '%', $parts ) );
		}

		return '( ' . implode( ' OR ', $clauses ) . ' )';
	}

	private static function sourceName( array $field ) {
		foreach ( [ '__name', 'name' ] as $property ) {
			if ( isset( $field[ $property ] ) && is_string( $field[ $property ] ) && '' !== $field[ $property ] ) {
				return $field[ $property ];
			}
		}

		return '';
	}

	private static function cleanNames( array $fieldNames ) {
		return array_values(
			array_unique(
				array_filter(
					array_map( 'strval', $fieldNames ),
					fn( $name ) => '' !== $name
				)
			)
		);
	}
}
