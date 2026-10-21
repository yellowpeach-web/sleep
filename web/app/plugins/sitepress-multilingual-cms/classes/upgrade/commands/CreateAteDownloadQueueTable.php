<?php

namespace WPML\TM\Upgrade\Commands;

use WPML\Upgrade\CreateTable;

class CreateAteDownloadQueueTable implements \IWPML_Upgrade_Command {

	const TABLE_NAME = 'icl_translation_downloads';

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

	/**
	 * The one copy of the DDL, so the install/activation path
	 * (`icl_sitepress_activate()`) and this upgrade command create the very
	 * same table. A site that was reset never sees the upgrade command again
	 * unless its licence loads TM, and activation has to stand on its own.
	 *
	 * @param \wpdb $wpdb
	 *
	 * @return bool
	 */
	public static function create_table_if_not_exists( $wpdb ) {
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		return CreateTable::create(
			$wpdb,
			$table_name,
			'
			CREATE TABLE IF NOT EXISTS `' . esc_sql( $table_name ) . '` (
			  `editor_job_id` BIGINT(20) UNSIGNED NOT NULL,
			  `download_url` VARCHAR(2000) NOT NULL,
			  `lock_timestamp` INT(11) UNSIGNED NULL,
			  PRIMARY KEY (`editor_job_id`)
			) ENGINE=INNODB'
		);
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
