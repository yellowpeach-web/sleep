<?php

namespace WPML\Upgrade\Commands;

use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\ContainerFreeServices;
use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\Repository\PreferenceRepository;

class OffloadMetaSettings implements \IWPML_Upgrade_Command {

	private $result = false;

	public function __construct( array $args ) {
		unset( $args );
	}

	public function run() {
		if ( ! PreferenceRepository::createTable() ) {
			return false;
		}

		$migrate = ContainerFreeServices::migrate();
		$state   = ContainerFreeServices::state();

		if ( $migrate->countSourceRecords() === 0 ) {
			$state->setMigrated( true );
			$this->result = true;
			return $this->result;
		}

		$state->markStarted();
		if ( ! $migrate->moveAll() ) {
			return false;
		}

		$checker = ContainerFreeServices::migrate();
		if ( ! $checker->ensureStoreMatchesBlob() ) {
			return false;
		}

		if ( $state->canCutOver() ) {
			$checker->cutover();
		}

		$this->result = true;
		return $this->result;
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
		return $this->result;
	}
}
