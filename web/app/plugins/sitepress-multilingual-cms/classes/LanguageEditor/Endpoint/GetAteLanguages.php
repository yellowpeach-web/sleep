<?php

namespace WPML\LanguageEditor\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;

class GetAteLanguages implements IHandler {

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( array( 'error' => 'forbidden' ) );
		}

		return Either::right( array( 'languages' => self::normalize( $this->api()->get_available_languages() ) ) );
	}

	public static function normalize( $feed ) {
		$out = array();
		foreach ( (array) $feed as $entry ) {
			$entry = (array) $entry;
			$iso   = isset( $entry['iso'] ) ? (string) $entry['iso'] : '';
			if ( '' === $iso ) {
				continue;
			}
			$out[] = array(
				'code' => $iso,
				'name' => isset( $entry['name'] ) && '' !== (string) $entry['name'] ? (string) $entry['name'] : $iso,
			);
		}
		usort(
			$out,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $out;
	}

	protected function api() {
		return \WPML\Container\make( \WPML_TM_ATE_API::class );
	}
}
