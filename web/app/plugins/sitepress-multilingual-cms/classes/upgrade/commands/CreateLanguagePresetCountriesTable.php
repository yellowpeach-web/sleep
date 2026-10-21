<?php

namespace WPML\Upgrade\Commands;

use WPML\Upgrade\CreateTable;

class CreateLanguagePresetCountriesTable implements \IWPML_Upgrade_Command, \IWPML_Pre_Setup_Upgrade_Command {

	const TABLE_NAME = 'icl_language_preset_countries';

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
				`preset_code` VARCHAR(40) NOT NULL,
				`country_code` VARCHAR(10) NOT NULL,
				`is_default` TINYINT(1) NOT NULL DEFAULT 0,
				`offerable_flags` VARCHAR(191) NULL DEFAULT NULL,
				`sort_order` INT NOT NULL DEFAULT 0,
				`code` VARCHAR(7) DEFAULT NULL,
				`default_locale` VARCHAR(35) DEFAULT NULL,
				`flag` VARCHAR(64) DEFAULT NULL,
				`picker_default` TINYINT(1) NOT NULL DEFAULT 0,
				UNIQUE KEY `preset_country` (`preset_code`, `country_code`),
				KEY `preset_code` (`preset_code`)
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
