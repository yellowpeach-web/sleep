<?php

namespace WPML\Upgrade\Commands;

class BackfillStringHasText implements \IWPML_Upgrade_Command {

	const CHUNK_SIZE = 20000;

	const CURSOR_OPTION = 'wpml_st_has_text_backfill_cursor';

	const DONE_OPTION = 'wpml_st_has_text_backfilled';

	private $upgrade_schema;

	private $result = false;

	public function __construct( array $args ) {
		$this->upgrade_schema = $args[0];
	}

	private function run() {
		$this->result = false;

		if (
			! $this->upgrade_schema->does_table_exist( 'icl_strings' )
			|| ! $this->upgrade_schema->does_column_exist( 'icl_strings', 'has_text' )
		) {
			return $this->result;
		}

		if ( get_option( self::DONE_OPTION ) ) {
			$this->result = true;

			return $this->result;
		}

		$wpdb   = $this->upgrade_schema->get_wpdb();
		$table  = $wpdb->prefix . 'icl_strings';
		$cursor = (int) get_option( self::CURSOR_OPTION, 0 );

		$last_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(id) FROM (SELECT id FROM `{$table}` WHERE id > %d ORDER BY id LIMIT %d) AS chunk",
				$cursor,
				self::CHUNK_SIZE
			)
		);

		if ( ! $last_id ) {
			update_option( self::DONE_OPTION, true, 'no' );
			delete_option( self::CURSOR_OPTION );

			if ( class_exists( '\WPML\ST\StringValue' ) ) {
				\WPML\ST\StringValue::resetReadyCache();
			}

			$this->result = true;

			return $this->result;
		}

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}` SET has_text = ( TRIM(value) <> '' ) WHERE id > %d AND id <= %d",
				$cursor,
				$last_id
			)
		);

		if ( false === $updated ) {
			return $this->result;
		}

		update_option( self::CURSOR_OPTION, $last_id, 'no' );

		return $this->result;
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return $this->run();
	}

	public function run_frontend() {
		return $this->run();
	}

	public function get_results() {
		return $this->result;
	}
}
