<?php

namespace WPML\Upgrade\Commands;

class DeleteIclAdminMessagesOption implements \IWPML_Upgrade_Command {

	const OPTIONS = [
		'icl_admin_messages',
		'wpml-invalid-php-extensions',
	];

	private $results;

	public function run() {
		foreach ( self::OPTIONS as $option ) {
			delete_option( $option );
		}

		$this->results = true;
		return $this->results;
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
		return $this->results;
	}
}
