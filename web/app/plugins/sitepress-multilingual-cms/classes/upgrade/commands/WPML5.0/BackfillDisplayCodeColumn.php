<?php

namespace WPML\Upgrade\Commands;

class BackfillDisplayCodeColumn implements \IWPML_Upgrade_Command, \IWPML_Pre_Setup_Upgrade_Command {

	const TABLE  = 'icl_languages';
	const COLUMN = 'display_code';

	private $schema;

	private $result;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public function run() {
		if ( \WPML_Settings_Failsafe_Loader::isUnrecoverable() ) {
			return false;
		}

		if ( ! $this->schema->does_column_exist( self::TABLE, self::COLUMN ) ) {
			return false;
		}

		$wpdb  = $this->schema->get_wpdb();
		$table = $wpdb->prefix . self::TABLE;

		$updated = $wpdb->query(
			"UPDATE `{$table}` SET display_code = code WHERE display_code IS NULL AND active = 1"
		);

		if ( $updated ) {
			do_action( 'wpml_update_active_languages' );
		}

		$this->result = true;

		return true;
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return $this->run();
	}

	public function run_frontend() {
		return $this->run();
	}

	public function get_results() {
		return $this->result;
	}
}
