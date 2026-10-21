<?php

namespace WPML\LanguageEditor\Save\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\LanguageEditor\Save\SaveEngine;
use function WPML\Container\make;

class SaveStatus implements IHandler {

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( [ 'error' => 'forbidden' ] );
		}

		$taskId = $data->get( 'taskId' );
		$task   = make( SaveEngine::class )->status( $taskId ? (int) $taskId : null );

		return Either::right(
			[
				'active' => $task && ! $task->isTerminal(),
				'task'   => $task ? $task->toResponse() : null,
			]
		);
	}
}
