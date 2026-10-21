<?php

namespace WPML\Upgrade\Commands;

class ResetStringWordCounts implements \IWPML_Upgrade_Command {

	const CURSOR_OPTION = 'wpml_reset_string_word_counts_cursor';

	const BATCH = 500;

	const TIME_BUDGET = 2.0;

	private $schema;

	private $timeBudget;

	private $result = false;

	public function __construct( array $args ) {
		$this->schema     = $args[0];
		$this->timeBudget = isset( $args[1] ) ? (float) $args[1] : self::TIME_BUDGET;
	}

	public function run_admin() {
		$deadline = microtime( true ) + $this->timeBudget;
		$cursor   = get_option( self::CURSOR_OPTION, [] );
		$cursor   = is_array( $cursor ) ? $cursor : [];

		foreach ( [ 'icl_strings', 'icl_string_packages' ] as $table ) {
			if ( ! $this->schema->does_table_exist( $table ) ) {
				continue;
			}

			$after = isset( $cursor[ $table ] ) ? (int) $cursor[ $table ] : 0;

			do {
				$ids = $this->nextIds( $table, $after );
				if ( ! $ids ) {
					break;
				}

				if ( false === $this->resetIds( $table, $ids ) ) {
					$this->result = false;

					return false;
				}

				$after            = (int) end( $ids );
				$cursor[ $table ] = $after;
				update_option( self::CURSOR_OPTION, $cursor, false );

				$more = count( $ids ) === self::BATCH;
				if ( $more && microtime( true ) >= $deadline ) {
					$this->result = false;

					return false;
				}
			} while ( $more );
		}

		delete_option( self::CURSOR_OPTION );
		$this->result = true;

		return true;
	}

	private function nextIds( $table, $after ) {
		$wpdb = $this->schema->get_wpdb();

		$ids = 'icl_strings' === $table
			? $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}icl_strings WHERE id > %d AND word_count IS NOT NULL ORDER BY id LIMIT %d",
					$after,
					self::BATCH
				)
			)
			: $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->prefix}icl_string_packages WHERE ID > %d AND word_count IS NOT NULL ORDER BY ID LIMIT %d",
					$after,
					self::BATCH
				)
			);

		return array_map( 'intval', (array) $ids );
	}

	private function resetIds( $table, array $ids ) {
		$wpdb = $this->schema->get_wpdb();

		if ( 'icl_strings' === $table ) {
			return $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}icl_strings SET word_count = NULL WHERE id IN (" . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')',
					$ids
				)
			);
		}

		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_string_packages SET word_count = NULL WHERE ID IN (" . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')',
				$ids
			)
		);
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return null;
	}

	public function get_results() {
		return $this->result;
	}
}
