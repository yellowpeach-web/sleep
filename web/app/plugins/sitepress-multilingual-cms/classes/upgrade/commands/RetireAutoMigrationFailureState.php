<?php

namespace WPML\TM\Upgrade\Commands;

use WPML\TM\ATE\ClonedSites\AutoMigration\Handler;

class RetireAutoMigrationFailureState implements \IWPML_Upgrade_Command {

	const LEGACY_FAILED_OPTION = 'wpml_ate_auto_migration_failed';

	private $result = false;

	public function run_admin() {
		delete_option( self::LEGACY_FAILED_OPTION );

		$data = get_option( Handler::OPTION_MIGRATION_DATA, null );
		if ( is_array( $data ) && ! isset( $data['organization_connected'] ) ) {
			delete_option( Handler::OPTION_MIGRATION_DATA );
		}

		$this->result = true;

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
}
