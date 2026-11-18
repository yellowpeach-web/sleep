<?php

namespace WPML\TM\Jobs;

use wpdb;

class StringBatchTitles {

	const TAXONOMY_LABEL_BATCH_NAME_PREFIX = 'translate everything|taxonomy-label|';

	private $wpdb;

	private $has_string_batches_table;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function get_titles( array $string_batch_ids ) {
		$ids = $this->clean_ids( $string_batch_ids );
		if ( ! $ids ) {
			return array();
		}

		$names = array();
		$wpdb  = $this->wpdb;
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, batch_name
				FROM {$wpdb->prefix}icl_translation_batches
				WHERE id IN (" . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')',
				$ids
			)
		);
		foreach ( (array) $rows as $row ) {
			$names[ (int) $row->id ] = (string) $row->batch_name;
		}

		$titles = array();
		foreach ( $ids as $id ) {
			$titles[ $id ] = $this->title( $id, isset( $names[ $id ] ) ? $names[ $id ] : '' );
		}

		return $titles;
	}

	public function title( $string_batch_id, $batch_name ) {
		$batch_name = (string) $batch_name;

		if ( 0 === strpos( $batch_name, self::TAXONOMY_LABEL_BATCH_NAME_PREFIX ) ) {
			$labels = $this->get_taxonomy_labels( (int) $string_batch_id );
			if ( '' !== $labels ) {
				return $labels;
			}
		}

		if ( '' !== trim( $batch_name ) ) {
			return $batch_name;
		}

		/* translators: Name of a batch of texts sent for translation, shown in the emails to translation managers and in the list of translation jobs when the batch has no name. */
		return __( 'Strings', 'wpml' );
	}

	public function count_strings( array $string_batch_ids ) {
		$ids = $this->clean_ids( $string_batch_ids );
		if ( ! $ids ) {
			return array();
		}

		$counts = array_fill_keys( $ids, 0 );

		if ( ! $this->has_string_batches_table() ) {
			return $counts;
		}

		$wpdb = $this->wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT batch_id, COUNT(*) AS strings
				FROM {$wpdb->prefix}icl_string_batches
				WHERE batch_id IN (" . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')
				GROUP BY batch_id',
				$ids
			)
		);
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row->batch_id ] = (int) $row->strings;
		}

		return $counts;
	}

	private function get_taxonomy_labels( $string_batch_id ) {
		if ( ! $this->has_string_batches_table() ) {
			return '';
		}

		$wpdb   = $this->wpdb;
		$values = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT strings.value
				FROM {$wpdb->prefix}icl_string_batches string_batches
				INNER JOIN {$wpdb->prefix}icl_strings strings
					ON strings.id = string_batches.string_id
				WHERE string_batches.batch_id = %d
				ORDER BY string_batches.id ASC",
				(int) $string_batch_id
			)
		);

		$values = array_values( array_unique( array_filter( array_map( 'trim', (array) $values ) ) ) );

		return implode( ', ', $values );
	}

	private function has_string_batches_table() {
		if ( null === $this->has_string_batches_table ) {
			$wpdb  = $this->wpdb;
			$table = $wpdb->prefix . 'icl_string_batches';

			$this->has_string_batches_table = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		}

		return $this->has_string_batches_table;
	}

	private function clean_ids( array $ids ) {
		return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	}
}
