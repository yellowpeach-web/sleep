<?php

namespace WPML\LanguageEditor\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\LanguageEditor\Presets\CatalogueSyncRunner;

class CheckCatalogueFreshness implements IHandler {

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( array( 'error' => 'forbidden' ) );
		}

		return Either::right( array( 'stale' => $this->runner()->isStale() ) );
	}

	protected function runner() {
		return CatalogueSyncRunner::create();
	}
}
