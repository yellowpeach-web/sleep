<?php

namespace WPML\TM\Upgrade\Commands;

class ResetClonedSiteLock implements \IWPML_Upgrade_Command {

	private $result = false;

	public function run_admin() {
		delete_option( ReleaseClonedSiteLockIntoRetry::LEGACY_LOCK_OPTION );
		$this->result = true;

		return $this->result;
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
