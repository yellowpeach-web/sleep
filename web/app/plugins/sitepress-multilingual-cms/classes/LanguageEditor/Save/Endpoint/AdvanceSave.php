<?php

namespace WPML\LanguageEditor\Save\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\LanguageEditor\Save\SaveEngine;
use function WPML\Container\make;

class AdvanceSave implements IHandler {

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( [ 'error' => 'forbidden' ] );
		}

		$taskId = (int) $data->get( 'taskId' );
		if ( ! $taskId ) {
			return Either::left( [ 'error' => 'missing_task_id' ] );
		}

		$task = make( SaveEngine::class )->advance( $taskId );
		if ( ! $task ) {
			return Either::left( [ 'error' => 'task_not_found' ] );
		}

		return Either::right( $task->toResponse() );
	}
}
