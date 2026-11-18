<?php

namespace WPML\TranslationPolicy\Migration;

use WPML\TranslationPolicy\CutoverCommand;
use WPML\TranslationPolicy\CutoverDryRunReport;
use WPML\TranslationPolicy\Policy\PolicyTable;

class SyncTranslationPolicy implements \IWPML_Upgrade_Command, CutoverCommand, CutoverDryRunReport {

	private $results;

	public function run(): bool {
		global $wpdb;

		if ( ! ( new PolicyTable( $wpdb ) )->create() ) {
			$this->results = false;

			return false;
		}

		PolicyMigration::create()->sync();
		$this->results = true;

		return true;
	}

	public function dryRunReport(): string {
		$plan = PolicyMigration::create()->plan();

		return sprintf( '%d row(s) to add, %d to update, %d to remove', $plan['added'], $plan['updated'], $plan['removed'] );
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
