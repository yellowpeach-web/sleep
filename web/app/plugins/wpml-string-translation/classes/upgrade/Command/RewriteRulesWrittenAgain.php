<?php

namespace WPML\ST\Upgrade\Command;

class RewriteRulesWrittenAgain implements \IWPML_St_Upgrade_Command {

	const OPTION_NAME = 'rewrite_rules';

	public function run() {
		delete_option( self::OPTION_NAME );
		return true;
	}

	public function run_ajax() {
		return false;
	}

	public function run_frontend() {
	}

	public static function get_command_id() {
		return __CLASS__;
	}
}
