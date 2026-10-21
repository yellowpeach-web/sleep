<?php

namespace WPML\LanguageEditor\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\Posts\TranslatedContentOfLanguages;

class CountLanguageContent implements IHandler {

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( array( 'error' => 'forbidden' ) );
		}

		$code = sanitize_text_field( (string) $data->get( 'code', '' ) );
		if ( '' === $code ) {
			return Either::left( array( 'error' => 'missing_code' ) );
		}

		return Either::right( TranslatedContentOfLanguages::counts( array( $code ) ) );
	}
}
