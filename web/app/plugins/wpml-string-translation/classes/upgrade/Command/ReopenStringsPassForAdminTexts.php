<?php

namespace WPML\ST\Upgrade\Command;

use WPML\ST\AdminTexts\StringsPassReopen;

class ReopenStringsPassForAdminTexts implements \IWPML_St_Upgrade_Command {

	private $wpdb;

	private $stringsPassReopen;

	public function __construct( \wpdb $wpdb, ?StringsPassReopen $stringsPassReopen = null ) {
		$this->wpdb              = $wpdb;
		$this->stringsPassReopen = $stringsPassReopen ?: new StringsPassReopen();
	}

	public function run() {
		if ( ! $this->stringsPassReopen->isTranslateEverythingOn() ) {
			return true;
		}

		if ( $this->hasRegisteredAdminText() ) {
			$this->stringsPassReopen->afterRegistration();
		}

		return true;
	}

	private function hasRegisteredAdminText() {
		$wpdb = $this->wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}icl_strings WHERE context LIKE %s LIMIT 1",
				$wpdb->esc_like( \WPML_Admin_Texts::DOMAIN_NAME_PREFIX ) . '%'
			)
		);
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
