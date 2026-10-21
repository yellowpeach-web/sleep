<?php

namespace WPML\Upgrade\Commands;

use WPML\Core\BackgroundTask\Model\BackgroundTask;

class WidenBackgroundTaskColumns implements \IWPML_Upgrade_Command {

	private $schema;

	private $result = false;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public function run() {
		$wpdb       = $this->schema->get_wpdb();
		$table_name = $wpdb->prefix . BackgroundTask::TABLE_NAME;

		$altered = true;

		$column = $wpdb->get_row( "SHOW COLUMNS FROM `{$table_name}` LIKE 'payload'", ARRAY_A );
		if ( $column && 'longtext' !== strtolower( $column['Type'] ) ) {
			$altered = false !== $wpdb->query(
				"ALTER TABLE `{$table_name}`
					MODIFY `completed_ids` LONGTEXT NULL DEFAULT NULL,
					MODIFY `payload` LONGTEXT NULL DEFAULT NULL"
			);

			if ( ! $altered ) {
				error_log(
					sprintf(
						'WPML: could not widen %s.payload/completed_ids to LONGTEXT (%s). Large background tasks will be dropped until this succeeds. The upgrade will retry on the next admin request.',
						$table_name,
						$wpdb->last_error
					)
				);
			}
		}

		$this->result = $altered;
		return $this->result;
	}

	public function run_admin() {
		return $this->run();
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
