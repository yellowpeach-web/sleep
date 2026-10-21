<?php

namespace WPML\LanguageEditor\Save\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\LanguageEditor\Cache;
use WPML\LanguageEditor\ResetDefaults\Applier;
use WPML\LanguageEditor\ResetDefaults\Detector;

class ResetLanguageDefaults implements IHandler {

	const ERROR_NO_GROUPS = 'missing_groups';

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( [ 'error' => 'forbidden' ] );
		}

		$groups = $this->groups( $data );

		if ( ! $groups ) {
			return Either::left( [ 'error' => self::ERROR_NO_GROUPS ] );
		}

		$outcome = $this->applier()->apply( $groups );

		$this->flushCaches();

		return Either::right(
			[
				'record'    => (int) $outcome['record'],
				'applied'   => $outcome['applied'],
				'remaining' => $outcome['remaining'],
				'partial'   => (bool) $outcome['partial'],
				'task'      => $outcome['task'],
				'codes'     => $outcome['codes'],
				'server'    => $this->serverState( $outcome['codes'] ),
			]
		);
	}

	protected function flushCaches() {
		Cache::flush();
	}

	protected function serverState( array $codes ) {
		return ProbeLanguageDefaults::serverState( $codes );
	}

	private function groups( Collection $data ) {
		$raw = $data->get( 'groups', [] );

		if ( ! is_array( $raw ) ) {
			return [];
		}

		$known = [ Detector::GROUP_FLAGS, Detector::GROUP_LABELS, Detector::GROUP_LOCALES ];

		return array_values( array_intersect( $known, array_map( 'strval', $raw ) ) );
	}

	protected function applier() {
		return new Applier();
	}
}
