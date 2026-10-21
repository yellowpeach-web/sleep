<?php

namespace WPML\Upgrade\Commands;

class AddBcp47ColumnToLanguages implements \IWPML_Upgrade_Command, \IWPML_Pre_Setup_Upgrade_Command {

	const TABLE  = 'icl_languages';
	const COLUMN = 'bcp_47';

	private $schema;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public function run() {
		$this->schema->ensure_columns(
			self::TABLE,
			[ self::COLUMN => 'VARCHAR(35) NULL DEFAULT NULL' ]
		);

		if ( ! $this->schema->does_table_exist( self::TABLE ) ) {
			return true;
		}

		if (
			! $this->schema->does_index_exist( self::TABLE, self::COLUMN )
			&& ! $this->schema->does_key_exist( self::TABLE, self::COLUMN )
		) {
			$this->schema->add_index( self::TABLE, self::COLUMN, '(`bcp_47`)' );
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
