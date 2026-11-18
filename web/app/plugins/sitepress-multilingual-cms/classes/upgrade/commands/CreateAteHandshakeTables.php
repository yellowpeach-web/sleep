<?php

namespace WPML\TM\Upgrade\Commands;

use WPML\TM\ATE\Handshake\Schema;

class CreateAteHandshakeTables implements \IWPML_Upgrade_Command {

	private $schema;

	private $result = false;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public function run() {
		$this->result = Schema::createTables( $this->schema->get_wpdb() );

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
