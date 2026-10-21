<?php

namespace WPML\Upgrade\Commands;

use WPML\Setup\Option;
use WPML\TM\ATE\TranslateEverything\OfferedTypeDefaults;

class BackfillTranslateEverythingSinceDates implements \IWPML_Upgrade_Command {

	private $offered_type_defaults;

	public function __construct( array $dependencies = [] ) {
		$this->offered_type_defaults = $dependencies[0] ?? null;
	}

	const INIT_PRIORITY = 1000;

	public function run_admin() {
		if ( Option::shouldTranslateEverything() ) {
			add_action( 'init', [ $this, 'backfillSinceDates' ], self::INIT_PRIORITY );
		}

		return true;
	}

	public function backfillSinceDates() {
		( $this->offered_type_defaults ?: new OfferedTypeDefaults() )->persist();
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return null;
	}

	public function get_results() {
		return true;
	}
}
