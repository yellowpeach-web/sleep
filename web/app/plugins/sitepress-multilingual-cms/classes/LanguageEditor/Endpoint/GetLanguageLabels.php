<?php

namespace WPML\LanguageEditor\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\LanguageEditor\PageData;

class GetLanguageLabels implements IHandler {

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( array( 'error' => 'forbidden' ) );
		}

		$code = sanitize_text_field( (string) $data->get( 'code', '' ) );
		if ( '' === $code ) {
			return Either::left( array( 'error' => 'missing_code' ) );
		}

		$displays = $data->get( 'displays', array() );
		$displays = is_array( $displays )
			? array_values( array_map( 'sanitize_text_field', $displays ) )
			: array();
		if ( empty( $displays ) ) {
			$displays = array( $code );
		}

		return Either::right(
			array( 'labels' => PageData::composedLabelsFor( $code, $displays ) )
		);
	}
}
