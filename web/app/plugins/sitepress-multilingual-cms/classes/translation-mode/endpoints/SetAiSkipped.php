<?php

namespace WPML\TranslationMode\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Right;
use WPML\Setup\Option;

class SetAiSkipped implements IHandler {

	public function run( Collection $data ) {
		$skipped = $data->get( 'skipped', false );
		$skipped = is_bool( $skipped ) ? $skipped : ( 'false' !== $skipped && (bool) $skipped );

		Option::setAiSkipped( $skipped );

		if ( $skipped ) {
			Option::setTranslateEverything( false );
		}

		return Right::of( true );
	}
}
