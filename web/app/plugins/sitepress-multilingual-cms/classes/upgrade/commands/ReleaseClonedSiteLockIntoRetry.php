<?php

namespace WPML\TM\Upgrade\Commands;

use WPML\TM\ATE\ClonedSites\ReconnectState;

class ReleaseClonedSiteLockIntoRetry implements \IWPML_Upgrade_Command {

	const LEGACY_LOCK_OPTION = 'otgs_wpml_tm_ate_cloned_site_lock';

	private $result = false;

	public function run_admin() {
		$this->result = $this->run();

		return $this->result;
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return null;
	}

	public function get_results() {
		return $this->result;
	}

	private function run() {
		$option = get_option( self::LEGACY_LOCK_OPTION, null );

		if ( $option === null || $option === false ) {
			return true;
		}

		if ( ! is_array( $option ) || ! $option ) {
			delete_option( self::LEGACY_LOCK_OPTION );
			delete_option( RetireAutoMigrationFailureState::LEGACY_FAILED_OPTION );

			return true;
		}

		$urls = ReconnectState::extractUrls( $option );

		ReconnectState::startDue( $urls['old_url'], $urls['new_url'] );

		delete_option( self::LEGACY_LOCK_OPTION );
		delete_option( RetireAutoMigrationFailureState::LEGACY_FAILED_OPTION );

		return true;
	}
}
