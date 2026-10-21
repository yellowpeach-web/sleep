<?php

namespace WPML\Upgrade\Commands;

use WPML\Upgrade\CreateTable;
use WPML\Media\Lookup\MediaLookupSchema;

class CreateMediaUrlLookupTable implements \IWPML_Upgrade_Command {

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
		$table_name = $wpdb->prefix . MediaLookupSchema::TABLE;

		$query = "
			CREATE TABLE IF NOT EXISTS `{$table_name}` (
				`url_hash` BINARY(16) NOT NULL,
				`language_code` VARCHAR(7) NOT NULL,
				`attachment_id` BIGINT UNSIGNED NOT NULL,
				`variant` TINYINT NOT NULL DEFAULT 0,
				`expires_at` INT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY (`url_hash`, `language_code`),
				KEY `attachment_id` (`attachment_id`)
			)
		";

		return CreateTable::create( $wpdb, $table_name, $query );
	}
}
