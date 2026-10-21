<?php

namespace WPML\Upgrade\Commands;

abstract class WidenColumn implements \IWPML_Upgrade_Command {

	abstract protected function get_table();

	abstract protected function get_column();

	abstract protected function get_column_definition();

	private $schema;

	private $result = false;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public function run() {
		if (
			! $this->schema->does_table_exist( $this->get_table() )
			|| ! $this->schema->does_column_exist( $this->get_table(), $this->get_column() )
		) {
			$this->result = true;
			return $this->result;
		}

		$this->result = false !== $this->schema->modify_column(
			$this->get_table(),
			$this->get_column(),
			$this->get_column_definition()
		);

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
