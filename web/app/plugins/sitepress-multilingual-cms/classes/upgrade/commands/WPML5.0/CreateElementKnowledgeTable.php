<?php

namespace WPML\Upgrade\Commands;

use WPML\Knowledge\ElementKnowledge;
use WPML\Upgrade\CreateTable;

class CreateElementKnowledgeTable implements \IWPML_Upgrade_Command {

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
		$table_name = $wpdb->prefix . ElementKnowledge::TABLE;

		$query = "
			CREATE TABLE IF NOT EXISTS `{$table_name}` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`entity_kind` VARCHAR(32) NOT NULL,
				`entity_id` VARCHAR(191) NOT NULL,
				`part` VARCHAR(191) NOT NULL DEFAULT '',
				`name` VARCHAR(64) NOT NULL,
				`scope` VARCHAR(64) NOT NULL DEFAULT '',
				`value` LONGTEXT NULL,
				`updated_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `entity_fact` (`entity_kind`, `entity_id`(64), `part`(64), `name`),
				KEY `fact` (`name`, `entity_kind`),
				KEY `by_scope` (`scope`)
			)
		";

		return CreateTable::create( $wpdb, $table_name, $query );
	}
}
