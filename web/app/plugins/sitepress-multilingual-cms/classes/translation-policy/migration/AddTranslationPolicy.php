<?php

namespace WPML\TranslationPolicy\Migration;

use WPML\TranslationPolicy\Policy\PolicyTable;

class AddTranslationPolicy implements \IWPML_Upgrade_Command {

	private $wpdb;

	private $sitepress;

	private $results;

	public function __construct( array $args ) {
		$this->wpdb      = $args[0];
		$this->sitepress = $args[1];
	}

	public function run(): bool {
		if ( ! ( new PolicyTable( $this->wpdb ) )->create() ) {
			$this->results = false;

			return false;
		}

		PolicyMigration::create( $this->wpdb, $this->sitepress )->addMissing();
		$this->results = true;

		return true;
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return $this->run();
	}

	public function get_results() {
		return $this->results;
	}
}
