<?php

namespace WPML\TM\Upgrade\Commands;

use WPML\Media\Option;
use WPML\Media\ActivateHandleMediaAuto;
use function WPML\Container\make;

class EnableHandleMediaAutoOptionForNewInstalls implements \IWPML_Upgrade_Command {

	public function run() {
		$startWpmlVersion  = get_option( \WPML_Installation::WPML_START_VERSION_KEY );
		$isNewInstallation = ICL_SITEPRESS_VERSION === $startWpmlVersion;
		$is_st_disabled    = ! defined( 'WPML_ST_VERSION' );
		if ( ! $isNewInstallation ) {
			Option::setShouldShowHandleMediaAutoNotice30DaysAfterUpgrade();
			return true;
		}

		if ( $is_st_disabled ) {
			Option::setShouldEnableHandleMediaAutoOnStActivation();
			Option::removeShouldShowHandleMediaAutoNotice30DaysAfterUpgrade();
			return true;
		}

		make( ActivateHandleMediaAuto::class )->activate();

		return true;
	}

	public function run_admin() {
		return $this->run();
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
