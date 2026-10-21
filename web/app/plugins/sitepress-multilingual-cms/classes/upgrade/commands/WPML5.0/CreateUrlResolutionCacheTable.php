<?php

namespace WPML\Upgrade\Commands;

use WPML\Upgrade\CreateTable;

class CreateUrlResolutionCacheTable implements \IWPML_Upgrade_Command {

	const TABLE_NAME = 'icl_url_resolution_cache';

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
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$query = "
			CREATE TABLE IF NOT EXISTS `{$table_name}` (
				`cache_key` BINARY(32) NOT NULL,
				`url_hash` BINARY(32) NOT NULL,
				`generation` BIGINT UNSIGNED NOT NULL,
				`source_url` TEXT NOT NULL,
				`source_language` VARCHAR(10) NOT NULL,
				`target_language` VARCHAR(10) NOT NULL,
				`state` VARCHAR(24) NOT NULL,
				`resolved_url` TEXT NULL,
				`source_object_kind` VARCHAR(16) NULL,
				`source_object_id` BIGINT UNSIGNED NULL,
				`translated_object_id` BIGINT UNSIGNED NULL,
				`translation_trid` BIGINT UNSIGNED NULL,
				`refresh_after` DATETIME NULL,
				`expires_at` DATETIME NULL,
				`refresh_requested` TINYINT(1) NOT NULL DEFAULT 0,
				`lease_token` BINARY(16) NULL,
				`lease_until` DATETIME NULL,
				`attempt_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				`next_attempt_at` DATETIME NULL,
				`created_at` DATETIME NOT NULL,
				`updated_at` DATETIME NOT NULL,
				PRIMARY KEY (`cache_key`),
				KEY `url_hash` (`url_hash`),
				KEY `queue` (`generation`, `state`, `refresh_requested`, `next_attempt_at`),
				KEY `lease_until` (`lease_until`),
				KEY `source_object` (`source_object_kind`, `source_object_id`),
				KEY `translated_object` (`translated_object_id`),
				KEY `translation_trid` (`translation_trid`),
				KEY `expires_at` (`expires_at`),
				KEY `generation_updated` (`generation`, `updated_at`)
			) ENGINE=InnoDB
		";

		return CreateTable::create( $wpdb, $table_name, $query );
	}
}
