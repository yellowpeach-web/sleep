<?php

namespace WPML\Upgrade\Commands;

class RemoveTruncatedLanguageNameRows implements \IWPML_Upgrade_Command, \IWPML_Pre_Setup_Upgrade_Command {

	const TABLE_NAME = 'icl_languages_translations';

	const TRUNCATED_CODES = [ 'hak-han', 'nan-han', 'yue-han', 'mey-lat' ];

	private $schema;

	private $result = false;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public function run() {
		$wpdb  = $this->schema->get_wpdb();
		$table = $wpdb->prefix . self::TABLE_NAME;

		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			$this->result = false;

			return $this->result;
		}

		$ok = true;
		foreach ( self::TRUNCATED_CODES as $code ) {
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM `{$table}`
					 WHERE language_code = %s
					   AND NOT EXISTS ( SELECT 1 FROM {$wpdb->prefix}icl_languages l WHERE l.code = %s )",
					$code,
					$code
				)
			);
			$ok = $ok && ( false !== $deleted );
		}

		$this->result = $ok;

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
}
