<?php

namespace WPML\LanguageEditor\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\TM\ATE\AutoTranslate\Endpoint\ActivateLanguage;
use function WPML\Container\make;

class ActivateLanguageTranslation implements IHandler {

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( array( 'error' => 'forbidden' ) );
		}

		return make( ActivateLanguage::class )->run( $data );
	}
}
