<?php

namespace WPML\LanguageEditor\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\LanguageEditor\LanguageCodeResolution;

class GetAteLookupChains implements IHandler {

	const MAX_CODES = 100;

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( array( 'error' => 'forbidden' ) );
		}

		$codes = $data->get( 'codes', array() );
		if ( ! is_array( $codes ) ) {
			return Either::left( array( 'error' => 'missing_codes' ) );
		}

		$chains = array();
		foreach ( array_slice( array_values( $codes ), 0, self::MAX_CODES ) as $raw ) {
			$code = sanitize_text_field( (string) $raw );
			if ( '' === $code || isset( $chains[ $code ] ) ) {
				continue;
			}
			$chains[ $code ] = LanguageCodeResolution::ateLookupChain( $code );
		}

		return Either::right( array( 'chains' => $chains ) );
	}
}
