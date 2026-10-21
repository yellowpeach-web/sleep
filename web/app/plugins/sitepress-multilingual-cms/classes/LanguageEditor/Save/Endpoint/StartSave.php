<?php

namespace WPML\LanguageEditor\Save\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\LanguageEditor\Save\SaveEngine;
use WPML\LanguageEditor\Save\SaveTaskRepository;
use function WPML\Container\make;

class StartSave implements IHandler {

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( [ 'error' => 'forbidden' ] );
		}

		$changes = array_values( (array) $data->get( 'changes', [] ) );
		if ( empty( $changes ) ) {
			return Either::left( [ 'error' => 'missing_changes' ] );
		}
		foreach ( $changes as &$change ) {
			if ( ! is_array( $change ) || empty( $change['code'] ) ) {
				return Either::left( [ 'error' => 'missing_changes' ] );
			}

			$change['refresh_names'] = isset( $change['refresh_names'] )
				&& (bool) filter_var( $change['refresh_names'], FILTER_VALIDATE_BOOLEAN );
		}
		unset( $change );

		$engine = make( SaveEngine::class );
		$task   = $engine->start( $changes );

		return Either::right( $task->toResponse() );
	}
}
