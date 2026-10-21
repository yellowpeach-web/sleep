<?php

namespace WPML\Upgrade\Commands;

use WPML\Core\BackgroundTask\Model\BackgroundTask;
use WPML\Upgrade\CreateTable;

class CreateBackgroundTaskTable implements \IWPML_Upgrade_Command {

	private $schema;

	private $result = false;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public function run() {
		$wpdb = $this->schema->get_wpdb();

		$this->result = self::create_table_if_not_exists( $wpdb );

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

	public static function create_table_if_not_exists( $wpdb ) {
		$table_name = $wpdb->prefix . BackgroundTask::TABLE_NAME;

		$query = "
			CREATE TABLE IF NOT EXISTS `{$table_name}` (
				`task_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`task_type` VARCHAR(500) NOT NULL,
				`task_status` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				`starting_date` DATETIME NULL,
				`total_count` INT UNSIGNED NOT NULL DEFAULT 0,
				`completed_count` INT UNSIGNED NOT NULL DEFAULT 0,
				`completed_ids` LONGTEXT NULL DEFAULT NULL,
				`payload` LONGTEXT NULL DEFAULT NULL,
				`retry_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0
			)
		";

		return CreateTable::create( $wpdb, $table_name, $query );
	}

}
