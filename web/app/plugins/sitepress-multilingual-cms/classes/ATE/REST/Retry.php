<?php

namespace WPML\TM\ATE\REST;

use WP_REST_Request;
use WPML\LIB\WP\User;
use WPML\TM\ATE\Retry\Arguments;
use WPML\TM\ATE\Retry\Process;
use WPML\TM\ATE\Retry\Result;
use WPML\Core\Security\ExecutionContext\ExecutionContextHolder;
use WPML\TM\Jobs\Authorization\AuthorizedJobResolver;
use WPML\TM\REST\Base;
use WPML_TM_ATE_AMS_Endpoints;
use function WPML\Container\make;

class Retry extends Base {
	public function get_routes() {
		return [
			[
				'route' => WPML_TM_ATE_AMS_Endpoints::RETRY_JOBS,
				'args'  => [
					'methods'  => 'POST',
					'callback' => [ $this, 'retry' ],
				],
			],
		];
	}

	public function get_allowed_capabilities( WP_REST_Request $request ) {
		return [
			'manage_options',
			'manage_translations',
			'translate',
		];
	}

	public function retry( WP_REST_Request $request ) {
		$jobsToProcess = $request->get_param( 'jobsToProcess' );

		if ( $jobsToProcess && ! User::canManageTranslations() ) {
			$resolver      = AuthorizedJobResolver::make();
			$context       = ExecutionContextHolder::current();
			$jobsToProcess = array_values(
				array_filter(
					(array) $jobsToProcess,
					function ( $jobId ) use ( $resolver, $context ) {
						return (bool) $resolver->byLocalId( $context, $jobId );
					}
				)
			);

			if ( ! $jobsToProcess ) {
				return (array) new Result();
			}
		}

		return (array) make( Process::class )->run( $jobsToProcess );
	}
}
