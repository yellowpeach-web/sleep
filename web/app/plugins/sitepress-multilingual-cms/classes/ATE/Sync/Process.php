<?php

namespace WPML\TM\ATE\Sync;

use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\TM\API\Job\Map;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\Cancel\PendingCancels;
use WPML\TM\ATE\ClonedSites\ApiCommunication;
use WPML\TM\ATE\Download\Job;
use WPML\TM\ATE\Log\EventsTypes;
use WPML_TM_ATE_AMS_Endpoints;
use WPML_TM_ATE_API;
use WPML_TM_ATE_Job_Repository;
use WPML\TM\ATE\Log\Storage;
use WPML\TM\ATE\Log\Entry;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;
use WPML\Translation\PreviousStateServiceFactory;
use WPML\Translation\TranslateJobErrorServiceFactory;
use function WPML\FP\pipe;
use WPML\TM\Jobs\JobLog;

class Process {

	const LOCK_RELEASE_TIMEOUT = 1 * MINUTE_IN_SECONDS;

	const SYNC_ALL_CHUNK = 50;

	const IN_FLIGHT_STATUSES = [
		TranslationStatus::WAITING_FOR_TRANSLATOR,
		TranslationStatus::IN_PROGRESS,
		TranslationStatus::ATE_NEEDS_RETRY,
		TranslationStatus::ATE_UNSOLVABLE,
	];

	private $api;

	private $ateRepository;

	public function __construct( WPML_TM_ATE_API $api, WPML_TM_ATE_Job_Repository $ateRepository ) {
		$this->api           = $api;
		$this->ateRepository = $ateRepository;
	}

	public function run( Arguments $args ) {
		$result          = new Result();

		if ( $args->page ) {
			JobLog::maybeInitRequest();
			JobLog::createNewGroup(
				JobLog::GROUP_ID_SYNC_JOBS,
				'Sending jobs to sync - run sync on pages',
				[
					'args' => get_object_vars( $args ),
				]
			);
			$result = $this->runSyncOnPages( $result, $args );
			JobLog::add(
				'Sync result',
				[
					'result' => get_object_vars( $result ),
				]
			);
			JobLog::finishCurrentGroup();
		} else {
			$includeManualAndLongstandingJobs  = (bool) Obj::propOr( true , 'includeManualAndLongstandingJobs', $args);
			$result = $this->runSyncInit( $result, $includeManualAndLongstandingJobs );
		}

		if ( ! $result->ateTransportFailure && ! $result->nextPage ) {
			PendingCancels::retry( $this->api );
		}

		return $result;
	}

	private function runSyncOnPages( Result $result, Arguments $args ) {
		$apiPage = $args->page - 1;
		$data    = $this->api->sync_page( $args->ateToken, $apiPage );

		$result->ateTransportFailure = self::isTransportFailure( $data );
		$result->ateRefusedLocally   = self::isRefusedLocally( $data );

		$jobs         = Obj::propOr( [], 'items', $data );
		$result->jobs = $this->handleJobs( $jobs );

		if ( !$result->jobs ){
			$log = Entry::createForType(
				EventsTypes::JOBS_SYNC,
				[
					'numberOfPages'     => $args->numberOfPages,
					'page'              => $args->page,
					'downloadQueueSize' => $result->downloadQueueSize,
					'nextPage'          => $result->nextPage,
				]
			);

			JobLog::add(
				'No jobs in sync results',
				$log
			);
			Storage::add( $log );
		}

		if ( isset( $data->eta ) && ( ! isset( $data->eta->available ) || false !== $data->eta->available ) ) {
			$result->eta            = $data->eta;
			$result->eta->available = true;
		}

		if ( $args->numberOfPages > $args->page ) {
			$result->nextPage      = $args->page + 1;
			$result->numberOfPages = $args->numberOfPages;
			$result->ateToken      = $args->ateToken;
		}

		return $result;
	}

	private function runSyncInit( Result $result, $includeManualAndLongstandingJobs = true ) {
		$jobsData   = $this->ateRepository->get_jobs_to_sync_with_element_ids( $includeManualAndLongstandingJobs );
		$ateJobIds  = $jobsData['ateJobIds'];
		$postIds    = $jobsData['postIds'];
		$stringIds  = $jobsData['stringIds'];
		$packageIds = $jobsData['packageIds'];
		$termIds    = isset( $jobsData['termIds'] ) ? $jobsData['termIds'] : [];

		if ( $ateJobIds ) {
			$total     = count( $ateJobIds );
			$ateJobIds = array_slice( $ateJobIds, 0, self::syncAllChunkSize() );

			JobLog::maybeInitRequest();
			JobLog::createNewGroup(
				JobLog::GROUP_ID_SYNC_JOBS,
				'Sending jobs to sync - sync init',
				[
					'ateJobIds'                        => $ateJobIds,
					'includeManualAndLongstandingJobs' => $includeManualAndLongstandingJobs,
				]
			);
			if ( count( $ateJobIds ) < $total ) {
				JobLog::add(
					'Sync init chunked',
					[
						'total' => $total,
						'sent'  => count( $ateJobIds ),
					]
				);
			}
			$this->ateRepository->increment_ate_sync_count( $ateJobIds );
			$data = $this->api->sync_all( $ateJobIds, $postIds, $stringIds, $packageIds, $termIds );

			$result->ateTransportFailure = self::isTransportFailure( $data );
			$result->ateRefusedLocally   = self::isRefusedLocally( $data );

			$jobs         = Obj::propOr( [], 'items', $data );
			$result->jobs = $this->handleJobs( $jobs );
			if ( isset( $data->eta ) && ( ! isset( $data->eta->available ) || false !== $data->eta->available ) ) {
				$result->eta            = $data->eta;
				$result->eta->available = true;
			}

			if ( isset( $data->next->pagination_token, $data->next->pages_number ) ) {
				$result->ateToken = $data->next->pagination_token;
				$result->numberOfPages = $data->next->pages_number;
				$result->nextPage = 1;
			}

			JobLog::add(
				'Sync results',
				[
					'result' => get_object_vars( $result ),
				]
			);
			JobLog::finishCurrentGroup();
		}

		return $result;
	}

	private static function isTransportFailure( $data ) {
		return $data instanceof \WP_Error && 'http_request_failed' === $data->get_error_code();
	}

	private static function isRefusedLocally( $data ) {
		return $data instanceof \WP_Error && ApiCommunication::RECONNECTING_ERROR_CODE === $data->get_error_code();
	}

	private static function syncAllChunkSize() {
		$size = apply_filters( 'wpml_ate_sync_all_chunk', self::SYNC_ALL_CHUNK );

		return ( is_int( $size ) && $size >= 1 ) ? $size : self::SYNC_ALL_CHUNK;
	}

	private function getAteJobIdsToSync( $includeManualAndLongstandingJobs = true ) {
		return $this->ateRepository
			->get_jobs_to_sync( $includeManualAndLongstandingJobs )
			->map_to_property( 'editor_job_id' );
	}

	private function handleJobs( array $items ) {
		return wpml_collect( $items )
			->map( [ Job::class, 'fromAteResponse' ] )
			->map( Obj::over( Obj::lensProp( 'jobId' ), Map::fromRid() ) )
			->map(
				function ( $job ) {
					return $this->readFailedByAte( $job );
				}
			)
			->map(
				function ( $job ) {
					if ( $job->isUnsolvable ) {
						$jobData                = Jobs::get( $job->jobId );
						$job->elementId         = $jobData->element_id ?? null;
						$job->originalElementId = $jobData->original_doc_id ?? null;
						unset( $jobData->elements );
						$job->errorData = $jobData;
					}
					return $job;
				}
			)
			->each(
				function ( $job ) {
					$this->reflectCancelledByAte( $job );
					if ( $this->isCanceledByUser( $job ) ) {
						$this->clearUnsolvableJob( $job );
					} elseif ( $job->isUnsolvable ) {
						$this->logUnsolvableJob( $job );
					}
				}
			)
			->toArray();
	}

	private function readFailedByAte( Job $job ) {
		$failedByAte = in_array(
			(int) $job->ateStatus,
			[
				WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_FAILED,
				WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_MISSING_LANGUAGE_MAPPING,
			],
			true
		);

		if ( $failedByAte && ! $job->isUnsolvable ) {
			$job->isUnsolvable = true;
			$job->errorType    = \WPML\TM\ATE\JobError\Recorder::TYPE_SYNC;
		}

		return $job;
	}

	private function reflectCancelledByAte( Job $job ) {
		$ateStatus = (int) $job->ateStatus;

		if (
			WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_CANCELLED_NOT_ENOUGH !== $ateStatus &&
			WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_CANCELED_BY_USER_SHOULD_HIDE !== $ateStatus
		) {
			return;
		}

		$jobId       = (int) $job->jobId;
		$freshStatus = Jobs::getStatus( $jobId );

		if ( null === $freshStatus || ICL_TM_COMPLETE === $freshStatus ) {
			JobLog::add( 'ate_cancelled_not_enough_row_left_alone', [
				'job_id'     => $jobId,
				'ate_job_id' => (int) $job->ateJobId,
				'status'     => $freshStatus,
			] );

			return;
		}

		$status = $freshStatus;

		if ( in_array( $freshStatus, self::IN_FLIGHT_STATUSES, true ) ) {
			$restored = PreviousStateServiceFactory::create()->revertInFlight(
				[ (int) Map::fromJobId( $jobId ) ],
				[ $freshStatus ],
				ICL_TM_ATE_CANCELLED
			);
			$status   = $restored ? reset( $restored ) : $freshStatus;
		} elseif ( ICL_TM_NOT_TRANSLATED === $freshStatus ) {
			Jobs::setStatus( $jobId, ICL_TM_ATE_CANCELLED );
			$status = ICL_TM_ATE_CANCELLED;
		}

		$job->status = $status;

		JobLog::add( 'ate_cancelled_not_enough_reflected', [
			'job_id'      => $jobId,
			'ate_job_id'  => (int) $job->ateJobId,
			'from_status' => $freshStatus,
			'to_status'   => $status,
		] );
	}

	private function logUnsolvableJob( $job ) {
		$recorder = new \WPML\TM\ATE\JobError\Recorder( TranslateJobErrorServiceFactory::create() );

		$recorder->record(
			$job->jobId,
			$job->ateJobId,
			'SyncError',
			$job->message ?: 'Job marked as unsolvable by ATE',
			[
				'ateStatus' => $job->ateStatus,
				'jobData'   => $job->errorData,
			]
		);
	}

	private function clearUnsolvableJob( $job ) {
		$recorder = new \WPML\TM\ATE\JobError\Recorder( TranslateJobErrorServiceFactory::create() );
		$recorder->clear( $job->jobId );
	}

	private function isCanceledByUser( $job ) {
		return WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_CANCELED_BY_USER_SHOULD_HIDE === (int) $job->ateStatus;
	}
}
