<?php

namespace WPML\LanguageEditor\Save\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\LanguageEditor\PageData;
use WPML\LanguageEditor\ResetDefaults\Budget;
use WPML\LanguageEditor\ResetDefaults\Detector;

class ProbeLanguageDefaults implements IHandler {

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( [ 'error' => 'forbidden' ] );
		}

		$differences = Detector::forDialog( $this->detector()->differences() );

		return Either::right( $differences + [ 'server' => static::serverState( null ) ] );
	}

	public static function serverState( ?array $codes ) {
		$active = array_values( (array) PageData::active() );
		$labels = (array) PageData::labels();

		if ( null !== $codes ) {
			$wanted = array_fill_keys( array_map( 'strval', $codes ), true );

			$active = array_values(
				array_filter(
					$active,
					function ( $row ) use ( $wanted ) {
						return isset( $row['code'] ) && isset( $wanted[ (string) $row['code'] ] );
					}
				)
			);
			$labels = array_intersect_key( $labels, $wanted );
		}

		return [
			'active' => $active,
			'labels' => $labels,
		];
	}

	protected function detector() {
		return new Detector( new Budget() );
	}
}
