<?php

namespace WPML\TM\ATE\Network\Endpoints;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\TM\ATE\Network\SubsiteAttachState;

class DismissSuggestion implements IHandler {

	public function run( Collection $data ) {
		SubsiteAttachState::dismissSuggestion();

		return Either::of( true );
	}
}
