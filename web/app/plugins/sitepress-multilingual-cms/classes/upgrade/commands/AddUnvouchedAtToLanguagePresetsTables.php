<?php

namespace WPML\Upgrade\Commands;

class AddUnvouchedAtToLanguagePresetsTables implements \IWPML_Upgrade_Command, \IWPML_Pre_Setup_Upgrade_Command {

	const COLUMN = 'unvouched_at';

	const TABLES = [
		CreateLanguagePresetsTable::TABLE_NAME,
		CreateLanguagePresetCountriesTable::TABLE_NAME,
	];

	const DEFINITION = 'DATETIME NULL DEFAULT NULL';

	private $schema;

	private $result = false;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	private function run() {
		$this->result = true;

		foreach ( self::TABLES as $table ) {
			if ( ! $this->schema->does_table_exist( $table ) ) {
				$this->result = false;
				continue;
			}
			if ( ! $this->schema->does_column_exist( $table, self::COLUMN ) ) {
				$this->schema->add_column( $table, self::COLUMN, self::DEFINITION );
			}
			if ( ! $this->schema->does_column_exist( $table, self::COLUMN ) ) {
				$this->result = false;
			}
		}

		return $this->result;
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
