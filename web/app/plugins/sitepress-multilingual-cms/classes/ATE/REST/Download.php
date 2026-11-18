<?php

namespace WPML\TM\ATE\REST;

use WP_REST_Request;
use WPML\Collect\Support\Collection;
use WPML\FP\Cast;
use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Obj;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\Download\Process;
use WPML\TM\ATE\Review\PreviewLink;
use WPML\TM\ATE\Review\ReviewStatus;
use WPML\TM\ATE\Review\StatusIcons;
use WPML\TM\ATE\SyncLock;
use WPML\TM\Jobs\JobLog;
use WPML\TM\REST\Base;
use WPML_TM_ATE_AMS_Endpoints;
use function WPML\Container\make;
use function WPML\FP\pipe;

class Download extends Base {
	public function get_routes() {
		return [
			[
				'route' => WPML_TM_ATE_AMS_Endpoints::DOWNLOAD_JOBS,
				'args'  => [
					'methods'  => 'POST',
					'callback' => [ $this, 'download' ],
					'args'     => [
						'lockKey' => self::getStringType(),
						'jobs'    => [
							'type'    => 'array',
							'default' => [],
							'items'   => [
								'type'       => 'object',
								'properties' => [
									'jobId'     => [ 'type' => 'integer' ],
									'ateJobId'  => [ 'type' => 'integer' ],
									'ateStatus' => [ 'type' => 'integer' ],
									'url'       => [ 'type' => 'string' ],
									'message'   => [ 'type' => 'string' ],
								],
							],
						],
					],
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

	public function download( WP_REST_Request $request ) {
		$lock = make( SyncLock::class );
		if ( ! $lock->create( $request->get_param( 'lockKey' ) ) ) {
			JobLog::runInGroup( JobLog::GROUP_ID_DOWNLOAD_JOBS, 'ATE jobs download (REST)', function () {
				JobLog::add( 'ate_sync_lock_busy', [ 'route' => 'ate/jobs/download' ] );
			} );

			return [];
		}

		try {
			return make( Process::class )->run( $this->authorizeBatch( (array) $request->get_param( 'jobs' ) ) )->all();
		} catch ( \Throwable $e ) {
			JobLog::runInGroup( JobLog::GROUP_ID_DOWNLOAD_JOBS, 'ATE jobs download (REST)', function () use ( $e ) {
				JobLog::addError( 'ate_sync_lock_released_on_error', [ 'route' => 'ate/jobs/download', 'message' => $e->getMessage() ] );
			} );

			throw $e;
		} finally {
			$lock->release();
		}
	}

	private function authorizeBatch( array $jobs ) {
		$resolver = \WPML\TM\Jobs\Authorization\AuthorizedJobResolver::make();
		$context  = \WPML\Core\Security\ExecutionContext\ExecutionContextHolder::current();

		$authorized = [];
		foreach ( $jobs as $job ) {
			$ateJobId      = (int) Obj::prop( 'ateJobId', $job );
			$suppliedJobId = (int) Obj::prop( 'jobId', $job );

			$resolved = $suppliedJobId
				? $resolver->byBoundPair( $context, $suppliedJobId, $ateJobId )
				: $resolver->byAteId( $context, $ateJobId );

			if ( ! $resolved ) {
				continue;
			}

			$authorized[] = Obj::assoc( 'jobId', $resolved->localId(), $job );
		}

		return $authorized;
	}
}
