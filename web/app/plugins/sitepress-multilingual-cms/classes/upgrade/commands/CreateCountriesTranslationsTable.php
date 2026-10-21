<?php

namespace WPML\Upgrade\Commands;

use WPML\Upgrade\CreateTable;

class CreateCountriesTranslationsTable implements \IWPML_Upgrade_Command, \IWPML_Pre_Setup_Upgrade_Command {

	const TABLE_NAME = 'icl_countries_translations';

	private $schema;

	private $result = false;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public function run() {
		$this->result = self::create_table_if_not_exists( $this->schema->get_wpdb() );

		return $this->result;
	}

	public static function create_table_if_not_exists( $wpdb ) {
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$query = "
			CREATE TABLE IF NOT EXISTS `{$table_name}` (
				`id` INT NOT NULL AUTO_INCREMENT,
				`country_code` VARCHAR(10) NOT NULL,
				`display_language_code` VARCHAR(7) NOT NULL,
				`name` VARCHAR(255) NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `country_code` (`country_code`, `display_language_code`)
			)
		";

		return CreateTable::create( $wpdb, $table_name, $query );
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
