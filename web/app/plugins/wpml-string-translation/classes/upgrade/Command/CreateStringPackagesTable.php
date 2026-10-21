<?php

namespace WPML\ST\Upgrade\Command;

use WPML\Upgrade\SchemaFacts;

class CreateStringPackagesTable implements \IWPML_St_Upgrade_Command {

	public function run() {
		try {
			\WPML_Package_Translation_Schema::run_update();
		} catch ( \Exception $e ) {
			return false;
		} finally {
			SchemaFacts::forget();
		}

		return true;
	}

	public function run_ajax() {
		return $this->run();
	}

	public function run_frontend() {
		return $this->run();
	}

	public static function get_command_id() {
		return __CLASS__ . '_' . \WPML_Package_Translation_Schema::REQUIRED_VERSION;
	}
}
