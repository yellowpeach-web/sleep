<?php

namespace WPML\Upgrade\Commands;

class AddPrecomputedColumnsToLanguagePresetCountries implements \IWPML_Upgrade_Command, \IWPML_Pre_Setup_Upgrade_Command {

	const TABLE = 'icl_language_preset_countries';

	private $schema;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	private function run() {
		if ( ! $this->schema->does_table_exist( self::TABLE ) ) {
			return true;
		}
		$columns = [
			'code'           => 'VARCHAR(7) NULL DEFAULT NULL',
			'default_locale' => 'VARCHAR(35) NULL DEFAULT NULL',
			'flag'           => 'VARCHAR(64) NULL DEFAULT NULL',
		];
		foreach ( $columns as $column => $attributes ) {
			if ( ! $this->schema->does_column_exist( self::TABLE, $column ) ) {
				$this->schema->add_column( self::TABLE, $column, $attributes );
			}
		}

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
		return true;
	}
}
