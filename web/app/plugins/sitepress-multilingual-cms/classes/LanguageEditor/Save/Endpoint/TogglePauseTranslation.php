<?php

namespace WPML\LanguageEditor\Save\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\LanguageEditor\TranslationPause;

abstract class TogglePauseTranslation implements IHandler {

	abstract protected function paused();

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( [ 'error' => 'forbidden' ] );
		}

		$code = trim( (string) $data->get( 'code', '' ) );

		if ( '' === $code ) {
			return Either::left( [ 'error' => 'missing_code' ] );
		}

		$error = TranslationPause::setPaused( $code, $this->paused() );

		if ( '' !== $error ) {
			return Either::left( [ 'error' => $error ] );
		}

		return Either::right(
			[
				'code'   => $code,
				'paused' => $this->paused(),
			]
		);
	}
}
