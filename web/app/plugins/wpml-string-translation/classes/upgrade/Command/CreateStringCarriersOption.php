<?php

namespace WPML\ST\Upgrade\Command;

class CreateStringCarriersOption implements \IWPML_St_Upgrade_Command {

	public function run() {
		add_option( \WPML_ST_Package_String_Carriers::OPTION, [], '', true );

		return true;
	}

	public function run_ajax() {
		return $this->run();
	}

	public function run_frontend() {
	}

	public static function get_command_id() {
		return __CLASS__;
	}
}
