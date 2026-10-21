<?php

namespace WPML\TM\ATE\AutoTranslate\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\LIB\WP\WordPress;
use function WPML\Container\make;

class Resume implements IHandler {

	public function run( Collection $data ) {
		$ateJobIds = $data->get( 'ateJobIds', [] );

		return WordPress::handleError( make( \WPML_TM_ATE_API::class )->resumeJobs( $ateJobIds ) );
	}
}
