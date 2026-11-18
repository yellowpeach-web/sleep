<?php

namespace WPML\Upgrade\Commands;

use WPML\EditHistory\Tables;
use WPML\Upgrade\CreateTable;

class CreateEditHistoryTables implements \IWPML_Upgrade_Command {

	private $wpdb;

	private $result = false;

	public function __construct( array $args ) {
		$this->wpdb = $args[0]->get_wpdb();
	}

	public function run(): bool {
		$entries = $this->wpdb->prefix . Tables::ENTRIES;
		$state   = $this->wpdb->prefix . Tables::STATE;

		$this->result = CreateTable::create( $this->wpdb, $entries, self::entriesStatement( $entries ) )
			&& CreateTable::create( $this->wpdb, $state, self::stateStatement( $state ) );

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

	public static function entriesStatement( string $table ): string {
		return "
			CREATE TABLE IF NOT EXISTS `{$table}` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`subject_kind` VARCHAR(16) NOT NULL,
				`subject_id` BIGINT UNSIGNED NOT NULL,
				`language` VARCHAR(7) NOT NULL,
				`created_at` INT UNSIGNED NOT NULL,
				`source` VARCHAR(20) NOT NULL,
				`editor` VARCHAR(32) NOT NULL DEFAULT '',
				`user_id` BIGINT UNSIGNED NULL,
				`units` LONGTEXT NOT NULL,
				`bytes` INT UNSIGNED NOT NULL,
				PRIMARY KEY (`id`),
				KEY `subject` (`subject_kind`, `subject_id`, `id`),
				KEY `user_id` (`user_id`),
				KEY `created_at` (`created_at`)
			)
		";
	}

	public static function stateStatement( string $table ): string {
		return "
			CREATE TABLE IF NOT EXISTS `{$table}` (
				`subject_kind` VARCHAR(16) NOT NULL,
				`subject_id` BIGINT UNSIGNED NOT NULL,
				`language` VARCHAR(7) NOT NULL DEFAULT '',
				`original_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
				`base_state` VARCHAR(8) NOT NULL DEFAULT 'none',
				`base_job_id` BIGINT UNSIGNED NULL,
				`base_ate_job_id` BIGINT UNSIGNED NULL,
				`base_delivery_id` BIGINT UNSIGNED NULL,
				`base_entry_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
				`base_applied_at` INT UNSIGNED NULL,
				`base` LONGTEXT NULL,
				`head_entry_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
				`head_hash` CHAR(32) NOT NULL DEFAULT '',
				`head` LONGTEXT NULL,
				`floor_entry_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
				`floor` LONGTEXT NULL,
				`sends` LONGTEXT NULL,
				`item_state` VARCHAR(8) NULL,
				`item_reason` VARCHAR(32) NULL,
				`copy` LONGTEXT NULL,
				`copy_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
				`stamps` LONGTEXT NULL,
				`updated_at` INT UNSIGNED NOT NULL,
				PRIMARY KEY (`subject_kind`, `subject_id`),
				KEY `item` (`item_state`, `base_applied_at`)
			)
		";
	}
}
