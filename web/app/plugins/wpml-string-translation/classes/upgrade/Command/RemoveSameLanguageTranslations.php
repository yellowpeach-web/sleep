<?php

namespace WPML\ST\Upgrade\Command;

use WPML\ST\SameLanguageTranslations;

class RemoveSameLanguageTranslations implements \IWPML_St_Upgrade_Command {

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function run() {
		if ( false !== ( new SameLanguageTranslations( $this->wpdb ) )->remove_all() ) {
			return true;
		}

		$wpdb  = $this->wpdb;
		$table = $wpdb->prefix . 'icl_string_translations';

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table;
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
