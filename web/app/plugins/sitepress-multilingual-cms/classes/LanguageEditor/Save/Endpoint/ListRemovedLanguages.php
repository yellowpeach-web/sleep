<?php

namespace WPML\LanguageEditor\Save\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\LanguageEditor\RemovedLanguages\Directory;

class ListRemovedLanguages implements IHandler {

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( [ 'error' => 'forbidden' ] );
		}

		return Either::right( [ 'languages' => Directory::all() ] );
	}
}
