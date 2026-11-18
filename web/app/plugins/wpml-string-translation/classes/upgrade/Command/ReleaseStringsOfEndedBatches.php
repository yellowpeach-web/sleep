<?php

namespace WPML\ST\Upgrade\Command;

use WPML\ST\Batch\Translation\OrphanedBatchSweep;

class ReleaseStringsOfEndedBatches implements \IWPML_St_Upgrade_Command {

	const MAX_PASSES = 10;

	private $sweep;

	public function __construct( OrphanedBatchSweep $sweep ) {
		$this->sweep = $sweep;
	}

	public function run() {
		for ( $pass = 0; $pass < self::MAX_PASSES; $pass++ ) {
			if ( 0 === $this->sweep->run() ) {
				return true;
			}
		}

		return false;
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
