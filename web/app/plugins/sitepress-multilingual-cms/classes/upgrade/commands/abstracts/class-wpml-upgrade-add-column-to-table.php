<?php

abstract class WPML_Upgrade_Add_Column_To_Table implements IWPML_Upgrade_Command {

	abstract protected function get_table();

	abstract protected function get_column();

	abstract protected function get_column_definition();

	private $upgrade_schema;

	public function __construct( array $args ) {
		$this->upgrade_schema = $args[0];
	}

	private function run() {
		$this->upgrade_schema->ensure_columns(
			$this->get_table(),
			[ $this->get_column() => $this->get_column_definition() ]
		);

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
