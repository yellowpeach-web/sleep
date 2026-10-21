<?php

namespace WPML\LanguageEditor\Save\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\LanguageEditor\Save\ChangeOrdering;
use WPML\LanguageEditor\Save\ImpactEstimator;
use function WPML\Container\make;

class EstimateImpact implements IHandler {

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( [ 'error' => 'forbidden' ] );
		}

		$changes = array_values( (array) $data->get( 'changes', [] ) );
		$token   = (string) $data->get( 'token', '' );

		$plan = ChangeOrdering::plan( array_filter( $changes, 'is_array' ) );
		if ( ! $plan['ok'] ) {
			return Either::left( [ 'error' => $plan['error'] ] );
		}

		$estimator = make( ImpactEstimator::class );
		$counts    = [
			'posts'   => 0,
			'links'   => 0,
			'strings' => 0,
		];
		foreach ( $changes as $change ) {
			if ( ! is_array( $change ) ) {
				continue;
			}
			$one = $estimator->estimate( $change );
			$counts['posts']   += (int) $one['posts'];
			$counts['links']   += (int) $one['links'];
			$counts['strings'] += (int) $one['strings'];
		}
		$counts['token'] = $token;

		return Either::right( $counts );
	}
}
