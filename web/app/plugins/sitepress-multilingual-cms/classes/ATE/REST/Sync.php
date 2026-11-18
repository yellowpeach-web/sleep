<?php

namespace WPML\TM\ATE\REST;

use WP_REST_Request;
use WPML\FP\Cast;
use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\API\ClientRestrictionState;
use WPML\TM\ATE\API\SpendCapState;
use WPML\TM\ATE\Handshake\DashboardCheck;
use WPML\TM\ATE\Sync\Arguments;
use WPML\TM\ATE\Sync\Process;
use WPML\TM\ATE\Sync\Result;
use WPML\TM\ATE\SyncLock;
use WPML\TM\Jobs\JobLog;
use WPML\TM\REST\Base;
use WPML_TM_ATE_AMS_Endpoints;
use WPML_TM_ATE_Job;
use function WPML\Container\make;

class Sync extends Base {
	public function get_routes() {
		return [
			[
				'route' => WPML_TM_ATE_AMS_Endpoints::SYNC_JOBS,
				'args'  => [
					'methods'  => 'POST',
					'callback' => [ $this, 'sync' ],
					'args'     => [
						'lockKey'                          => self::getStringType(),
						'ateToken'                         => self::getStringType(),
						'nextPage'                         => self::getIntType(),
						'numberOfPages'                    => self::getIntType(),
						'includeManualAndLongstandingJobs' => [
							'type'              => 'boolean',
							'validate_callback' => 'WPML_REST_Arguments_Validation::boolean',
							'sanitize_callback' => 'WPML_REST_Arguments_Sanitation::boolean',
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

	public function sync( WP_REST_Request $request ) {
		$args                    = new Arguments();
		$args->ateToken          = $request->get_param( 'ateToken' );
		$args->page              = $request->get_param( 'nextPage' );
		$args->numberOfPages     = $request->get_param( 'numberOfPages' );
		$args->includeManualAndLongstandingJobs = $request->get_param( 'includeManualAndLongstandingJobs' );

		$lock = make( SyncLock::class );
		if ( $lock->create( $request->get_param( 'lockKey' ) ) ) {
			try {
				$result = make( Process::class )->run( $args );

				$this->fallback_to_unstuck_completed_jobs( $result->jobs );
			} catch ( \Throwable $e ) {
				JobLog::runInGroup( JobLog::GROUP_ID_SYNC_JOBS, 'ATE jobs sync (REST)', function () use ( $e ) {
					JobLog::addError( 'ate_sync_lock_released_on_error', [ 'route' => 'ate/jobs/sync', 'message' => $e->getMessage() ] );
				} );

				throw $e;
			} finally {
				$lock->release();
			}
		} else {
			JobLog::runInGroup( JobLog::GROUP_ID_SYNC_JOBS, 'ATE jobs sync (REST)', function () {
				JobLog::add( 'ate_sync_lock_busy', [ 'route' => 'ate/jobs/sync' ] );
			} );

			$result = new Result();
		}

		( new DashboardCheck() )->afterSync( $result->jobs );

		$restriction = ClientRestrictionState::get();
		if ( $restriction ) {
			$result->clientRestriction = $restriction->toArray();
		}

		$spendCap = SpendCapState::getShown();
		if ( $spendCap ) {
			$result->spendCap = $spendCap->toArray();
		}

		if ( is_array( $result->jobs ) && $result->jobs ) {
			$resolver     = \WPML\TM\Jobs\Authorization\AuthorizedJobResolver::make();
			$context      = \WPML\Core\Security\ExecutionContext\ExecutionContextHolder::current();
			$result->jobs = array_values( array_filter(
				$result->jobs,
				function ( $job ) use ( $resolver, $context ) {
					return (bool) $resolver->byLocalId( $context, (int) Obj::prop( 'jobId', $job ) );
				}
			) );
		}

		return (array) $result;
	}

	private function fallback_to_unstuck_completed_jobs( &$jobs ) {
		if ( ! is_array( $jobs ) ) {
			return;
		}

		foreach ( $jobs as $job ) {
			if (
				! is_object( $job )
				|| ! property_exists( $job, 'jobId' )
				|| ! property_exists( $job, 'ateStatus' )
				|| ! property_exists( $job, 'status' )
			) {
				continue;
			}

			if ( WPML_TM_ATE_Job::ATE_JOB_DELIVERED === $job->ateStatus ) {
				$job->status = ICL_TM_COMPLETE;
				Jobs::setStatus( $job->jobId, ICL_TM_COMPLETE );
			}
		}
	}
}
