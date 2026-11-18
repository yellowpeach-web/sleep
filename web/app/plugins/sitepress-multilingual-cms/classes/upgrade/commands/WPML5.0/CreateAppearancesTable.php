<?php

namespace WPML\Upgrade\Commands;

use WPML\Knowledge\AppearanceRepository;
use WPML\Upgrade\CreateTable;

class CreateAppearancesTable implements \IWPML_Upgrade_Command {

	private $schema;

	private $result = false;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public function run() {
		$this->result = self::create_table_if_not_exists( $this->schema->get_wpdb() );

		return $this->result;
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return $this->run();
	}

	public function get_results() {
		return $this->result;
	}

	public static function create_table_if_not_exists( $wpdb ) {
		$table_name = $wpdb->prefix . AppearanceRepository::TABLE;

		$query = "
			CREATE TABLE IF NOT EXISTS `{$table_name}` (
				`element_type` VARCHAR(62) NOT NULL,
				`element_id` BIGINT UNSIGNED NOT NULL,
				`first_seen` DATETIME NOT NULL,
				`last_seen` DATETIME NOT NULL,
				`seen_by` ENUM('admin','visitor') NULL,
				PRIMARY KEY (`element_type`, `element_id`),
				KEY `type_last_seen` (`element_type`, `last_seen`)
			)
		";

		return CreateTable::create( $wpdb, $table_name, $query );
	}
}
