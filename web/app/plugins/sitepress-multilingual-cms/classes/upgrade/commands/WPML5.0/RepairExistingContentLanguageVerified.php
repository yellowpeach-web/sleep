<?php

namespace WPML\Upgrade\Commands;

class RepairExistingContentLanguageVerified implements \IWPML_Upgrade_Command {

	const KEY = 'existing_content_language_verified';

	private $sitepress;

	private $wpdb;

	private $results;

	public function __construct( array $args ) {
		$this->sitepress = $args[0];
		$this->wpdb      = $args[1];
	}

	public function run() {
		if ( $this->needsRepair() ) {
			$this->sitepress->set_setting( self::KEY, 1, true );
		}

		$this->results = true;

		return $this->results;
	}

	private function needsRepair() {
		if ( ! $this->sitepress->get_setting( 'setup_complete' ) || $this->sitepress->get_setting( self::KEY ) ) {
			return false;
		}

		return $this->hasRealTranslation() || count( (array) $this->sitepress->get_setting( 'active_languages', [] ) ) > 1;
	}

	private function hasRealTranslation() {
		$wpdb = $this->wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE source_language_code IS NOT NULL AND translation_id > %d LIMIT 1",
				0
			)
		);
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
