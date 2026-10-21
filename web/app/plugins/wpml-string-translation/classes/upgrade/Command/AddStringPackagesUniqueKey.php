<?php

namespace WPML\ST\Upgrade\Command;

use WPML\ST\PackageTranslation\UniqueKey;

class AddStringPackagesUniqueKey implements \IWPML_St_Upgrade_Command {

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function run() {
		if ( ! \WPML\Upgrade\SchemaFacts::hasTable( 'icl_string_packages' ) ) {
			return false;
		}

		UniqueKey::add( $this->wpdb );

		return true;
	}

	public function run_ajax() {
		return false;
	}

	public function run_frontend() {
		return false;
	}

	public static function get_command_id() {
		return __CLASS__;
	}
}
