<?php

namespace WPML\Upgrade\Commands;

use WPML\Compatibility\Divi\ThemeBuilderRepair\Runner;

class LinkDiviThemeBuilderLayouts implements \IWPML_Upgrade_Command {

	private $wpdb;

	private $sitepress;

	private $runner;

	private $results;

	public function __construct( array $args ) {
		$this->wpdb      = $args[0];
		$this->sitepress = $args[1];
		$this->runner    = isset( $args[2] ) && $args[2] instanceof Runner ? $args[2] : null;
	}

	public function run() {
		$runner = $this->runner ?: Runner::forSite( $this->wpdb, $this->sitepress );
		$runner->linkLayouts( false );

		$this->results = true;

		return $this->results;
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
