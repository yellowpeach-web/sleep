<?php

namespace WPML\TM\ATE\ClonedSites\AutoMigration\Endpoints;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\TM\ATE\ClonedSites\AutoMigration\Handler;
use WPML\TM\ATE\ClonedSites\AutoMigration\RequestHost;
use WPML\TM\ATE\ClonedSites\MigrationLogger;
use function WPML\Container\make;

class Disconnect implements IHandler {

	public function run( Collection $data ) {
		if ( ! RequestHost::servesRecordedCopy() ) {
			return Either::left( 'Disconnect is only available on the site the migration was recorded for' );
		}

		if ( Handler::isSameRecord() ) {
			return Either::left( 'Disconnect is not available: this site kept its own record when it moved' );
		}

		MigrationLogger::beginClonedSiteAction( 'disconnect' );

		try {
			$amsApi = make( \WPML_TM_AMS_API::class );

			MigrationLogger::disconnectRequestSent();
			$result = $amsApi->disconnect();
			MigrationLogger::disconnectResponse( $result );

			if ( is_wp_error( $result ) && (int) $result->get_error_code() === 409 ) {
				MigrationLogger::disconnectAlreadyIndependent();
				$this->finalize();
				return Either::of( [ 'already_independent' => true ] );
			}

			if ( $result === null || is_wp_error( $result ) ) {
				return Either::left( 'Disconnect failed' );
			}

			$this->finalize();

			return Either::of( true );
		} finally {
			MigrationLogger::end();
		}
	}

	private function finalize() {
		$hasSitekey = Handler::hasSitekey();
		if ( $hasSitekey ) {
			Handler::clearMigrationData();
		} else {
			Handler::setOrganizationConnected( false );
		}
		MigrationLogger::clonedSiteActionFinalized( $hasSitekey, false );
	}
}
