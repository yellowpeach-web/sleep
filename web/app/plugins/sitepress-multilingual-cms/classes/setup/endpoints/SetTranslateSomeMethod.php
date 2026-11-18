<?php

namespace WPML\Setup\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Left;
use WPML\FP\Right;
use WPML\TranslationPolicy\Settings\SetupChoice;

class SetTranslateSomeMethod implements IHandler {

	public function run( Collection $data ) {
		if ( SetupChoice::saveTranslateSomeMethod( (string) $data->get( 'method' ) ) ) {
			return new Right( true );
		}

		return new Left( 'The method must be ptc or self.' );
	}
}
