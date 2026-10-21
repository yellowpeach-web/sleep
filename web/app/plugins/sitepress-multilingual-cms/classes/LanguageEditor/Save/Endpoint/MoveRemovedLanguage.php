<?php

namespace WPML\LanguageEditor\Save\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\LanguageEditor\RemovedLanguages\MoveService;

class MoveRemovedLanguage implements IHandler {

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( [ 'error' => 'forbidden' ] );
		}

		$source      = trim( (string) $data->get( 'source', '' ) );
		$target      = trim( (string) $data->get( 'target', '' ) );
		$confirmed   = (bool) filter_var( $data->get( 'confirmed', false ), FILTER_VALIDATE_BOOLEAN );
		$onCollision = trim( (string) $data->get( 'on_collision', MoveService::ON_COLLISION_SKIP ) );
		$dryRun      = (bool) filter_var( $data->get( 'dry_run', false ), FILTER_VALIDATE_BOOLEAN );

		$result = MoveService::move( $source, $target, $confirmed, $onCollision, $dryRun );

		if ( '' !== $result['error'] ) {
			return Either::left( [ 'error' => $result['error'] ] );
		}

		return Either::right( $result );
	}
}
