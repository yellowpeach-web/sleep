<?php

namespace WPML\TM\ATE\ClonedSites;

use function WPML\Container\make;

class ConnectionProbe {

	public function run( $forced = true ) {
		if ( $forced ) {
			ApiCommunication::forceProbe();
		}

		try {
			if ( ReconnectState::SERVICE_AMS === ReconnectState::service() && $this->canProbeAms() ) {
				$this->ams()->get_status();
			} else {
				$this->ate()->get_catalogue_version();
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}

		if ( $forced ) {
			ApiCommunication::cancelForcedProbe();
		}

		return ReconnectState::get();
	}

	private function canProbeAms() {
		$registration = $this->ams()->get_registration_data();

		return is_array( $registration ) && ! empty( $registration['shared'] );
	}

	private function ate() {
		return make( \WPML_TM_ATE_API::class );
	}

	private function ams() {
		return make( \WPML_TM_AMS_API::class );
	}
}
