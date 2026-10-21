<?php

namespace WPML\TM\ATE\ClonedSites\AutoMigration\Endpoints;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\TM\ATE\ClonedSites\AutoMigration\Handler;

class Dismiss implements IHandler {

	public function run( Collection $data ) {
		Handler::clearMigrationData();

		return Either::of( true );
	}
}
