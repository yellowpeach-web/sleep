<?php

namespace WPML\TM\ATE\Retry;

use WPML\Collect\Support\Collection;
use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\BatchResult;
use WPML\Core\Component\Translation\Domain\Entity\JobError;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\API\ClientRestrictionState;
use WPML\TM\ATE\AutomaticTranslationCapabilities;
use WPML\TM\ATE\Cancel\CancelSource;
use WPML\TM\ATE\JobCreation\AteCreateReconciler;
use WPML\TM\ATE\JobCreation\CreateFailureRecord;
use WPML\TM\ATE\JobError\Recorder;
use WPML\TM\ATE\Log\Entry;
use WPML\TM\ATE\Log\EventsTypes;
use WPML\TM\ATE\Log\Storage;
use WPML\TM\ATE\Receive\JobKind;
use WPML\TM\Jobs\JobLog;
use WPML\Translation\CancelJobsServiceFactory;
use WPML\TranslationPolicy\Policy\Policy;
use WPML\Utilities\Lock;
use WPML_TM_ATE_Job_Repository;
use function WPML\Container\make;

class Process {

	const JOBS_PROCESSED_PER_REQUEST = 10;

	const LOCK_NAME = 'ate_retry';

	const LOCK_RELEASE_TIMEOUT = MINUTE_IN_SECONDS;

	const NOT_AUTOMATIC_TYPE = 'type';

	const NOT_AUTOMATIC_LANGUAGE = 'language';

	private $ateRepository;

	private $trigger;

	private $recorder;

	public function __construct(
		WPML_TM_ATE_Job_Repository $ateRepository,
		Trigger $trigger,
		$recorder = null
	) {
		$this->ateRepository = $ateRepository;
		$this->trigger       = $trigger;
		$this->recorder      = $recorder;
	}

	public function run( $jobsToProcess ) {
		if ( ! $jobsToProcess ) {
			return $this->runRetry( null );
		}

		return JobLog::runInGroup(
			JobLog::GROUP_ID_RETRIES,
			'ATE job retry (requested)',
			function () use ( $jobsToProcess ) {
				return $this->runRetry( $jobsToProcess );
			}
		);
	}

	private function runRetry( $jobsToProcess ) {
		$result = new Result();

		$lock = make( Lock::class, [ ':name' => self::LOCK_NAME ] );

		if ( ! $lock->create( self::LOCK_RELEASE_TIMEOUT ) ) {
			JobLog::add( 'ate_retry_skipped_lock_busy', [] );

			return $result;
		}

		try {
			if ( $jobsToProcess ) {
				$jobs   = wpml_collect( $jobsToProcess );
				$result = $this->isHeldByRestriction( $jobs ) ? $result : $this->retry( $result, $jobs );
			} else {
				$result = $this->runRetryInit( $result );
			}

			if ( $result->jobsToProcess->isEmpty() && $this->trigger->isRetryRequired() ) {
				$this->trigger->setLastRetry( time() );

				do_action( 'wpml_tm_ate_retry_cadence' );
			}
		} finally {
			$lock->release();
		}

		return $result;
	}

	private function runRetryInit( Result $result ) {
		$wpmlJobIds = $this->getWpmlJobIdsToRetry();

		if ( $this->trigger->isRetryRequired() ) {
			$this->giveUpJobsAtTheCeiling( $wpmlJobIds );
		}

		if ( $this->trigger->isRetryRequired() && ! $wpmlJobIds->isEmpty() && ! $this->isHeldByRestriction( $wpmlJobIds ) ) {
			$result = JobLog::runInGroup(
				JobLog::GROUP_ID_RETRIES,
				'ATE job retry (cadence)',
				function () use ( $result, $wpmlJobIds ) {
					return $this->retry( $result, $wpmlJobIds );
				}
			);
		}

		return $result;
	}

	private function isHeldByRestriction( Collection $jobs ) {
		if ( ! ClientRestrictionState::isRestricted() ) {
			return false;
		}

		Storage::add(
			Entry::createForType(
				EventsTypes::JOB_RETRY,
				[
					'held'         => 'client-restricted',
					'debtor_block' => ClientRestrictionState::debtorBlock(),
					'job_ids'      => array_map( 'intval', array_values( $jobs->toArray() ) ),
				]
			),
			true
		);

		return true;
	}

	private function retry( Result $result, Collection $jobs ) {
		$featureOn = AutomaticTranslationCapabilities::shouldTranslateEverythingFresh();

		$jobsChunks = $this->withoutHeldBack( $jobs, $featureOn )->chunk( self::JOBS_PROCESSED_PER_REQUEST );
		$chunk      = $jobsChunks->isEmpty() ? wpml_collect( [] ) : $jobsChunks->shift();

		$result->processed     = $this->handleJobs( $this->stillParked( $chunk ) );
		$result->jobsToProcess = $jobsChunks->flatten( 1 );

		if ( $result->processed && $featureOn && ! AutomaticTranslationCapabilities::shouldTranslateEverythingFresh() ) {
			JobLog::add( 'ate_retry_tea_off_after_binding', [ 'job_ids' => $result->processed ] );
			do_action( 'wpml_cancel_all_automatic_jobs', make( BatchResult::class ) );
		}

		return $result;
	}

	private function withoutHeldBack( Collection $jobs, $featureOn ) {
		if ( $jobs->isEmpty() ) {
			return $jobs;
		}

		$heldWhileOff    = [];
		$heldNotEligible = [];
		$typeLeft        = [];

		$retryable = $jobs
			->filter(
				function ( $jobId ) use ( $featureOn, &$heldWhileOff, &$heldNotEligible, &$typeLeft ) {
					$jobId = (int) $jobId;
					if ( ! Jobs::isAutomatic( $jobId ) ) {
						return true;
					}

					if ( ! $featureOn ) {
						$heldWhileOff[] = $jobId;

						return false;
					}

					$reason = $this->whyNoLongerAutomatic( $jobId );
					if ( self::NOT_AUTOMATIC_TYPE === $reason ) {
						$typeLeft[] = $jobId;

						return false;
					}

					if ( self::NOT_AUTOMATIC_LANGUAGE === $reason ) {
						$heldNotEligible[] = $jobId;

						return false;
					}

					return true;
				}
			)
			->values();

		if ( $heldWhileOff ) {
			JobLog::add( 'ate_retry_tea_off_holding_parked', [ 'job_ids' => $heldWhileOff ] );
		}

		if ( $heldNotEligible ) {
			JobLog::add( 'ate_retry_not_eligible_holding_parked', [ 'job_ids' => $heldNotEligible ] );
		}

		if ( $typeLeft ) {
			$this->cancelTypeLeft( $typeLeft );
		}

		return $retryable;
	}

	private function whyNoLongerAutomatic( $jobId ) {
		if ( AteCreateReconciler::heldSend( $jobId ) || JobKind::isTermBundle( $jobId ) ) {
			return null;
		}

		$policy = Policy::reader();
		if ( ! $policy->jobTypeIsAutomatic( $jobId ) ) {
			return self::NOT_AUTOMATIC_TYPE;
		}

		return 'manual' === $policy->jobMode( $jobId ) ? self::NOT_AUTOMATIC_LANGUAGE : null;
	}

	private function cancelTypeLeft( array $jobIds ) {
		try {
			$result = CancelJobsServiceFactory::create()->cancelJobs( $jobIds, false, CancelSource::TYPE_LEFT_TRANSLATE_EVERYTHING );
		} catch ( \Throwable $e ) {
			JobLog::addError( 'ate_retry_type_left_cancel_failed', [ 'job_ids' => $jobIds, 'error' => $e->getMessage() ] );

			return;
		}

		JobLog::add(
			'ate_retry_type_left_cancelled_parked',
			[
				'job_ids'   => $jobIds,
				'cancelled' => $result['cancelledJobIds'],
			]
		);
	}

	private function stillParked( Collection $chunk ) {
		return $chunk
			->filter(
				function ( $jobId ) {
					return ICL_TM_ATE_NEEDS_RETRY === Jobs::getStatus( (int) $jobId );
				}
			)
			->values();
	}

	private function getWpmlJobIdsToRetry() {
		return wpml_collect(
			$this->ateRepository
				->get_jobs_to_retry( JobError::RETRY_LIMITS[ JobError::TYPE_CREATE ] )
				->map_to_property( 'translate_job_id' )
		);
	}

	private function giveUpJobsAtTheCeiling( Collection $picked ) {
		$limit     = JobError::RETRY_LIMITS[ JobError::TYPE_CREATE ];
		$parked    = wpml_collect( $this->ateRepository->get_jobs_to_retry()->map_to_property( 'translate_job_id' ) );
		$atCeiling = array_diff(
			array_map( 'intval', $parked->toArray() ),
			array_map( 'intval', $picked->toArray() )
		);

		foreach ( $atCeiling as $jobId ) {
			$failure = CreateFailureRecord::read( $jobId );
			$message = $failure && '' !== $failure['message']
				? $failure['message']
				: sprintf( 'The job could not be sent to automatic translation in %d tries.', $limit );

			if ( $failure ) {
				CreateFailureRecord::write( $jobId, $failure['kind'], $failure['message'] );
			}

			$this->recorder()->giveUp(
				$jobId,
				0,
				JobError::TYPE_CREATE,
				$message,
				[
					'kind'    => $failure ? $failure['kind'] : '',
					'retries' => $limit,
				]
			);
		}
	}

	private function recorder() {
		if ( ! $this->recorder ) {
			$this->recorder = new Recorder();
		}

		return $this->recorder;
	}

	private function handleJobs( Collection $items ) {
		if ( $items->isEmpty() ) {
			return [];
		}

		$termBundles = make( TermBundles::class );
		list( $bundleJobIds, $otherJobIds ) = $termBundles->partition( $items->toArray() );

		if ( $otherJobIds ) {
			do_action( 'wpml_added_translation_jobs', [ 'local' => $otherJobIds ], Jobs::SENT_RETRY );
		}

		if ( $bundleJobIds ) {
			$termBundles->resend( $bundleJobIds );
		}

		return $items->toArray();
	}
}
