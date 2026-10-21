<?php

namespace WPML\Upgrade\Commands;

use WPML\Upgrade\CreateTable;

class CreateLanguagePresetsTable implements \IWPML_Upgrade_Command, \IWPML_Pre_Setup_Upgrade_Command {

	const TABLE_NAME = 'icl_language_presets';

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
				`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`code` VARCHAR(40) NOT NULL,
				`english_name` VARCHAR(128) NOT NULL,
				`script` VARCHAR(16) NULL DEFAULT NULL,
				`language` VARCHAR(40) NOT NULL DEFAULT '',
				`type` VARCHAR(20) NOT NULL DEFAULT 'national',
				`country_mode` VARCHAR(20) NOT NULL DEFAULT 'required',
				`language_flag` VARCHAR(64) NULL DEFAULT NULL,
				`wp_code` VARCHAR(35) NULL DEFAULT NULL,
				`major` TINYINT NOT NULL DEFAULT 0,
				`visibility_tier` VARCHAR(16) NOT NULL DEFAULT 'all',
				`rtl` TINYINT(1) NULL DEFAULT NULL,
				UNIQUE KEY `code` (`code`),
				KEY `type` (`type`),
				KEY `language` (`language`),
				KEY `visibility_tier` (`visibility_tier`)
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
		return $this->run();
	}

	public function get_results() {
		return $this->result;
	}
}
