<?php

namespace WPML\LanguageEditor\Save\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\LanguageEditor\Save\SaveEngine;
use WPML\LanguageEditor\Save\Endpoint\AdvanceSave;
use function WPML\Container\make;

class ResumeSave implements IHandler {

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( [ 'error' => 'forbidden' ] );
		}

		$taskId = (int) $data->get( 'taskId' );
		if ( ! $taskId ) {
			return Either::left( [ 'error' => 'missing_task_id' ] );
		}

		$task = make( SaveEngine::class )->resume( $taskId );
		if ( ! $task ) {
			return Either::left( [ 'error' => 'task_not_found' ] );
		}

		$response          = $task->toResponse();
		$response['nonce'] = wp_create_nonce( AdvanceSave::class );

		return Either::right( $response );
	}
}
