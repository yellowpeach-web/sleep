<?php

abstract class WPML_Upgrade_Add_Column_To_Table implements IWPML_Upgrade_Command {

	abstract protected function get_table();

	abstract protected function get_column();

	abstract protected function get_column_definition();

	private $upgrade_schema;

	public function __construct( array $args ) {
		$this->upgrade_schema = $args[0];
	}

	private function run( $given_up ) {
		$schema = $this->upgrade_schema;
		$table  = $this->get_table();
		$column = $this->get_column();

		if ( $schema->add_column_gave_up( $table, $column ) ) {
			return $given_up;
		}

		if ( ! $schema->does_table_exist( $table ) || $schema->does_column_exist( $table, $column ) ) {
			return true;
		}

		return false !== $schema->add_column( $table, $column, $this->get_column_definition() );
	}

	public function run_admin() {
		return (bool) $this->run( false );
	}

	public function run_ajax() {
		return $this->run( null );
	}

	public function run_frontend() {
		return $this->run( null );
	}

	public function get_results() {
		return true;
	}
}
