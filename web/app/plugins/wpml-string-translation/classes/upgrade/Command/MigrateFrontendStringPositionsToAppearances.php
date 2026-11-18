<?php

namespace WPML\ST\Upgrade\Command;

class MigrateFrontendStringPositionsToAppearances implements \IWPML_St_Upgrade_Command {

	public function run() {
		return $this->import();
	}

	public function run_ajax() {
		return $this->import();
	}

	public function run_frontend() {
		return $this->import();
	}

	public static function get_command_id() {
		return __CLASS__;
	}

	private function import() {
		return is_int( apply_filters( 'wpml_appearance_import_strings', null ) );
	}
}
