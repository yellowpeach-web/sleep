<?php

namespace WPML\TM\ATE\PullDelivery;

use WPML\FP\Obj;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\API\ClientRestrictionState;
use WPML\TM\ATE\CollectLock;
use WPML\TM\ATE\API\SpendCapState;
use WPML\TM\ATE\Receive\ClientEdits;
use WPML\TM\ATE\Receive\JobKind;
use WPML\TM\ATE\Receive\TranslationApplier;
use WPML\TM\ATE\JobError\Recorder;
use WPML\TM\ATE\Sync\Arguments;
use WPML\TM\ATE\Sync\Process;
use WPML\TM\ATE\SyncLock;
use WPML\TM\Jobs\JobLog;
use WPML_TM_ATE_AMS_Endpoints;
use WPML_TM_ATE_API;
use function WPML\Container\make;

class Collector {

	const EXIT_NO_PENDING = 'no_pending';

	const EXIT_LOCK_BUSY = 'lock_busy';

	const EXIT_NOTHING_READY = 'nothing_ready';

	const EXIT_DRAINED = 'drained';

	const EXIT_BOUNDED_JOBS = 'bounded_jobs';

	const EXIT_BOUNDED_TIME = 'bounded_time';

	const EXIT_ERROR = 'error';

	const EXIT_ATE_TIMEOUT = 'ate_timeout';

	const EXIT_ATE_REFUSED_LOCALLY = 'ate_refused_locally';

	private $lock;

	private $runKey;

	private $applyLock;

	private $syncProcess;

	private $applier;

	private $pendingJobs;

	private $meter;

	private $reported = [];

	private $parkedSetChecked = false;

	private $ateJobs;

	private $jobErrorRecorder;

	private $budget = Cadence::COLLECT_MAX_SECONDS;

	private $slowestApplyEstimate = 0.0;

	private $slowestApply = 0.0;

	private $appliesInRun = 0;

	private $appliedSoFar = [];

	public function __construct(
		CollectLock $lock,
		Process $syncProcess,
		TranslationApplier $applier,
		PendingJobs $pendingJobs,
		Meter $meter,
		SyncLock $applyLock,
		?\WPML_TM_ATE_Jobs $ateJobs = null,
		?Recorder $jobErrorRecorder = null
	) {
		$this->lock             = $lock;
		$this->syncProcess      = $syncProcess;
		$this->applier          = $applier;
		$this->pendingJobs      = $pendingJobs;
		$this->meter            = $meter;
		$this->applyLock        = $applyLock;
		$this->ateJobs          = $ateJobs;
		$this->jobErrorRecorder = $jobErrorRecorder;
	}

	public function run() {
		$this->meter->start();

		$ownsBackgroundRequest = JobLog::maybeInitBackgroundRequest( 'wpml-pull-collect' );

		JobLog::createNewGroup(
			JobLog::GROUP_ID_DOWNLOAD_JOBS,
			'ATE pull collection',
			[ 'mode' => State::mode() ]
		);

		$holdsLock = false;

		try {
			JobLog::add( 'pull_collect_start', [ 'mode' => State::mode() ] );

			$this->runKey = $this->lock->create();

			if ( ! $this->runKey ) {
				return $this->finish( self::EXIT_LOCK_BUSY, [], 0, null );
			}

			$holdsLock = true;

			State::onCollectionStart();

			$pendingBefore = $this->pendingJobs->summary();

			if ( $pendingBefore['count'] < 1 ) {
				return $this->finish( self::EXIT_NO_PENDING, [], 0, $pendingBefore );
			}

			list( $applied, $stillInProgress, $reason ) = $this->collectReadyJobs();

			return $this->finish( $reason, $applied, $stillInProgress, null );
		} catch ( \Throwable $e ) {
			JobLog::addError(
				'pull_collect_failed',
				[
					'message' => $e->getMessage(),
					'origin'  => $e->getFile() . ':' . $e->getLine(),
				]
			);

			return $this->finish( self::EXIT_ERROR, $this->appliedSoFar, 0, null );
		} finally {
			if ( $holdsLock ) {
				$this->lock->release();
			}

			JobLog::finishCurrentGroup();

			if ( $ownsBackgroundRequest ) {
				JobLog::flushBackgroundRequest();
			}
		}
	}

	private function collectReadyJobs() {
		$this->budget    = Cadence::collectBudgetSeconds();
		$deadline        = $this->now() + $this->budget;
		$maxJobs         = Cadence::maxJobsPerRun();
		$applied         = [];
		$stillInProgress = 0;
		$reason          = self::EXIT_NOTHING_READY;

		$this->slowestApplyEstimate = max( 0, (int) State::getKey( 'slowest_apply_ms', 0 ) ) / 1000;
		$this->slowestApply         = 0.0;
		$this->appliesInRun         = 0;
		$this->appliedSoFar         = [];

		$kept = $this->keptJobsStillPending();

		if ( $kept ) {
			$this->reported = [];

			list( $applied, $rest, $bound ) = $this->applyWithinBudget( $kept, [], $deadline, $maxJobs );

			ReadyQueue::put( $rest );

			return [
				$applied,
				(int) State::getKey( 'still_in_progress', 0 ),
				$bound ?: ( $applied ? self::EXIT_DRAINED : self::EXIT_NOTHING_READY ),
			];
		}

		$this->reported = [
			'insufficient_balance_job_ids' => [],
			'insufficient_balance_ate_job_ids' => [],
			'payment_pending_job_ids'     => [],
			'payment_pending_ate_job_ids' => [],
			'unsolvable_job_ids'           => [],
			'eta_available'                => false,
			'eta_minutes'                  => 0,
		];

		$this->parkedSetChecked = false;

		$args                                   = new Arguments();
		$args->includeManualAndLongstandingJobs = true;

		do {
			$result = $this->syncProcess->run( $args );

			$this->lock->create( (string) $this->runKey );

			if ( ! empty( $result->ateTransportFailure ) || ! empty( $result->ateRefusedLocally ) ) {
				$this->reported = [];

				return [
					$applied,
					$stillInProgress,
					empty( $result->ateTransportFailure ) ? self::EXIT_ATE_REFUSED_LOCALLY : self::EXIT_ATE_TIMEOUT,
				];
			}

			$jobs   = is_array( $result->jobs ) ? $result->jobs : [];

			$ready            = [];
			$pageInProgress   = 0;

			foreach ( $jobs as $job ) {
				if ( $this->isReady( $job ) ) {
					$ready[] = $job;
				} elseif ( $this->isCanceledByUser( $job ) ) {
					continue;
				} elseif ( $this->isUnsolvable( $job ) ) {
					$this->reported['unsolvable_job_ids'][] = (int) Obj::prop( 'jobId', $job );
				} elseif ( $this->isOutOfCredit( $job ) ) {
					$this->reported['insufficient_balance_job_ids'][] = (int) Obj::prop( 'jobId', $job );

					$ateJobId = (int) Obj::prop( 'ateJobId', $job );

					if ( $ateJobId ) {
						$this->reported['insufficient_balance_ate_job_ids'][] = $ateJobId;
					}
				} elseif ( $this->isWaitingForPayment( $job ) ) {
					$this->reported['payment_pending_job_ids'][] = (int) Obj::prop( 'jobId', $job );

					$ateJobId = (int) Obj::prop( 'ateJobId', $job );

					if ( $ateJobId ) {
						$this->reported['payment_pending_ate_job_ids'][] = $ateJobId;
					}
				} elseif ( $this->isDeliveredTermJob( $job ) ) {
					if ( ! $this->completeDeliveredTermJob( $job ) ) {
						$pageInProgress++;
					}
				} else {
					$pageInProgress++;
				}
			}

			$this->recordEta( $result );
			$this->putFirstParkedSetOnFile();

			$stillInProgress += $pageInProgress;

			list( $applied, $rest, $bound ) = $this->applyWithinBudget( $ready, $applied, $deadline, $maxJobs );

			if ( $bound ) {
				ReadyQueue::put( $rest );

				return [ $applied, $stillInProgress, $bound ];
			}

			if ( $ready ) {
				$reason = self::EXIT_DRAINED;
			}

			$args = $this->nextPageArguments( $result );
		} while ( $args );

		return [ $applied, $stillInProgress, $reason ];
	}

	private function putFirstParkedSetOnFile() {
		if ( $this->parkedSetChecked || ! $this->reported['insufficient_balance_ate_job_ids'] ) {
			return;
		}
		$this->parkedSetChecked = true;

		if ( ! State::getFresh()['insufficient_balance_ate_job_ids'] ) {
			State::update( [
				'insufficient_balance_job_ids'     => $this->reported['insufficient_balance_job_ids'],
				'insufficient_balance_ate_job_ids' => $this->reported['insufficient_balance_ate_job_ids'],
			] );
		}
	}

	private function keptJobsStillPending() {
		$kept = ReadyQueue::read();

		if ( $kept ) {
			$waiting = array_flip( $this->pendingJobs->stillPending( wpml_collect( $kept )->pluck( 'ateJobId' )->toArray() ) );
			$kept    = array_values(
				array_filter(
					$kept,
					function ( $job ) use ( $waiting ) {
						return isset( $waiting[ (int) $job->ateJobId ] );
					}
				)
			);
		}

		if ( ! $kept ) {
			ReadyQueue::forget();
		}

		return $kept;
	}

	private function applyWithinBudget( array $jobs, array $applied, $deadline, $maxJobs ) {
		$jobs = $this->withoutJobsWaitingOutABackoff( $jobs );

		foreach ( $jobs as $index => $job ) {
			if ( count( $applied ) >= $maxJobs ) {
				return [ $applied, array_slice( $jobs, $index ), self::EXIT_BOUNDED_JOBS ];
			}

			$now = $this->now();

			if ( $this->appliesInRun > 0 ) {
				$estimate = $this->slowestApply;
			} else {
				$estimate = $this->slowestApplyEstimate < $this->budget ? $this->slowestApplyEstimate : 0.0;
			}

			if ( $now + $estimate >= $deadline ) {
				return [ $applied, array_slice( $jobs, $index ), self::EXIT_BOUNDED_TIME ];
			}

			$this->appliesInRun++;

			try {
				if ( $this->applyJob( $job ) ) {
					$applied[]          = $job;
					$this->appliedSoFar = $applied;

					ApplyBackoff::clear( $job );
				}
			} catch ( \Throwable $e ) {
				$this->onApplyThrew( $job, $e );
			}

			$this->slowestApply = max( $this->slowestApply, $this->now() - $now );
		}

		return [ $applied, [], null ];
	}

	private function withoutJobsWaitingOutABackoff( array $jobs ) {
		$ready   = [];
		$waiting = [];

		foreach ( $jobs as $job ) {
			if ( ApplyBackoff::isWaiting( $job ) ) {
				$waiting[] = (int) Obj::prop( 'jobId', $job );
			} else {
				$ready[] = $job;
			}
		}

		if ( $waiting ) {
			JobLog::add( 'pull_collect_apply_deferred', [ 'jobIds' => $waiting ] );
		}

		return $ready;
	}

	private function onApplyThrew( $job, \Throwable $e ) {
		$retryIn = 0;

		try {
			$retryIn = ApplyBackoff::recordFailure( $job );
		} finally {
			JobLog::addError(
				'pull_collect_apply_failed',
				[
					'jobId'    => (int) Obj::prop( 'jobId', $job ),
					'ateJobId' => (int) Obj::prop( 'ateJobId', $job ),
					'message'  => $e->getMessage(),
					'origin'   => $e->getFile() . ':' . $e->getLine(),
					'retry_in' => $retryIn,
				]
			);
		}
	}

	protected function now() {
		return microtime( true );
	}

	private function applyJob( $job ) {
		$jobId = $this->boundJobId( $job );

		if ( ! $jobId ) {
			return false;
		}

		if ( ! $this->applyLock->create() ) {
			JobLog::add( 'pull_collect_apply_skipped_lock_busy', [ 'jobId' => $jobId ] );

			return false;
		}

		try {
			return $this->applyJobUnderLock( $job, $jobId );
		} finally {
			$this->applyLock->release();
		}
	}

	private function boundJobId( $job ) {
		$ateJobId = (int) Obj::prop( 'ateJobId', $job );
		$ateJobs  = $ateJobId > 0 ? $this->ateJobs() : null;
		$byAteId  = $ateJobs ? (int) $ateJobs->get_wpml_job_id( $ateJobId ) : 0;

		return $byAteId > 0 ? $byAteId : (int) Obj::prop( 'jobId', $job );
	}

	private function ateJobs() {
		if ( ! $this->ateJobs ) {
			$this->ateJobs = make( \WPML_TM_ATE_Jobs::class );
		}

		return $this->ateJobs instanceof \WPML_TM_ATE_Jobs ? $this->ateJobs : null;
	}

	private function applyJobUnderLock( $job, $jobId ) {
		$wpmlJob = Jobs::get( $jobId );

		if ( ! $wpmlJob ) {
			if ( ! JobKind::isTaxonomyTerm( $jobId ) ) {
				JobLog::add( 'pull_collect_skipped', [ 'jobId' => $jobId, 'reason' => 'job_missing' ] );

				return false;
			}

			$job->automatic = true;

			return $this->recordOutcome( $jobId, $this->applier->applyForTerm( $jobId ) );
		}

		if ( $this->namesAnotherOriginal( $wpmlJob, (int) Obj::prop( 'jobId', $job ) ) ) {
			return $this->refuseMismatch( $wpmlJob, $job );
		}

		if ( ClientEdits::areInTheWay( $wpmlJob, Obj::prop( 'editor_job_id', $wpmlJob ) ) ) {
			JobLog::add( 'pull_collect_skipped', [ 'jobId' => $jobId, 'reason' => ClientEdits::REASON ] );

			return false;
		}

		$job->automatic = (bool) Obj::propOr( false, 'automatic', $wpmlJob );

		$outcome = $this->applier->applyForPost(
			Obj::prop( 'job_id', $wpmlJob ),
			Obj::prop( 'original_doc_id', $wpmlJob )
		);

		return $this->recordOutcome( $jobId, $outcome );
	}

	private function namesAnotherOriginal( $wpmlJob, $syncNamedJobId ) {
		if ( ! $syncNamedJobId || (int) Obj::prop( 'job_id', $wpmlJob ) === $syncNamedJobId ) {
			return false;
		}

		$syncNamedJob = Jobs::get( $syncNamedJobId );

		if ( ! $syncNamedJob ) {
			return false;
		}

		$boundOriginal = (int) Obj::prop( 'original_doc_id', $wpmlJob );
		$namedOriginal = (int) Obj::prop( 'original_doc_id', $syncNamedJob );

		return $boundOriginal && $namedOriginal && $boundOriginal !== $namedOriginal;
	}

	private function refuseMismatch( $wpmlJob, $job ) {
		$jobId    = (int) Obj::prop( 'job_id', $wpmlJob );
		$ateJobId = (int) Obj::prop( 'ateJobId', $job );

		$message = sprintf(
			'The delivery of ATE job %1$d was refused: our translation system reports the job\'s source as the translation of a different content (original %2$d) than the local job holding this ATE job id (job %3$d, original %4$d). Applying it would write another original\'s translation, so nothing was written. Repair the local job-to-ATE mapping (WPML > Support > Troubleshooting > Synchronize local job ids with ATE jobs) and resend the content for translation.',
			$ateJobId,
			(int) Obj::prop( 'original_doc_id', Jobs::get( (int) Obj::prop( 'jobId', $job ) ) ),
			$jobId,
			(int) Obj::prop( 'original_doc_id', $wpmlJob )
		);

		JobLog::add(
			'pull_collect_refused_wrong_original',
			[
				'jobId'         => $jobId,
				'ateJobId'      => $ateJobId,
				'syncNamedJobId' => (int) Obj::prop( 'jobId', $job ),
			]
		);

		$this->recorder()->giveUp( $jobId, $ateJobId, Recorder::TYPE_APPLY, $message, [ 'sync_named_job_id' => (int) Obj::prop( 'jobId', $job ) ] );

		return false;
	}

	private function recorder() {
		if ( ! $this->jobErrorRecorder ) {
			$this->jobErrorRecorder = new Recorder();
		}

		return $this->jobErrorRecorder;
	}

	private function recordOutcome( $jobId, $outcome ) {
		if ( TranslationApplier::APPLIED === $outcome ) {
			JobLog::add( 'pull_collect_applied', [ 'jobId' => $jobId ] );

			return true;
		}

		JobLog::add( 'pull_collect_skipped', [ 'jobId' => $jobId, 'reason' => $outcome ] );

		return false;
	}

	private function nextPageArguments( $result ) {
		if ( ! $result->ateToken || ! $result->nextPage ) {
			return null;
		}

		$args                                   = new Arguments();
		$args->ateToken                         = $result->ateToken;
		$args->page                             = $result->nextPage;
		$args->numberOfPages                    = $result->numberOfPages;
		$args->includeManualAndLongstandingJobs = true;

		return $args;
	}

	private function recordEta( $result ) {
		$eta = isset( $result->eta ) ? $result->eta : null;

		$available = is_object( $eta ) && ( ! isset( $eta->available ) || false !== $eta->available );

		$this->reported['eta_available'] = $available;
		$this->reported['eta_minutes']   = $available && isset( $eta->estimated_minutes )
			? (int) $eta->estimated_minutes
			: 0;
	}

	private function isReady( $job ) {
		$status = (int) Obj::prop( 'ateStatus', $job );

		return in_array(
			$status,
			[
				WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_TRANSLATED,
				WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_DELIVERING,
				WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_EDITED,
			],
			true
		) && ! $this->isUnsolvable( $job );
	}

	private function isDeliveredTermJob( $job ) {
		return WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_DELIVERED === (int) Obj::prop( 'ateStatus', $job )
			&& JobKind::isTaxonomyTerm( (int) Obj::prop( 'jobId', $job ) );
	}

	private function completeDeliveredTermJob( $job ) {
		$jobId = (int) Obj::prop( 'jobId', $job );

		if ( ! $this->applyLock->create() ) {
			JobLog::add( 'pull_collect_complete_skipped_lock_busy', [ 'jobId' => $jobId ] );

			return false;
		}

		try {
			Jobs::setStatus( $jobId, ICL_TM_COMPLETE );
			JobLog::add( 'pull_collect_completed_delivered_term_job', [ 'jobId' => $jobId ] );

			return true;
		} finally {
			$this->applyLock->release();
		}
	}

	private function isCanceledByUser( $job ) {
		return WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_CANCELED_BY_USER_SHOULD_HIDE === (int) Obj::prop( 'ateStatus', $job );
	}

	private function isUnsolvable( $job ) {
		return (bool) Obj::prop( 'isUnsolvable', $job )
			|| in_array(
				(int) Obj::prop( 'ateStatus', $job ),
				[
					WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_FAILED,
					WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_MISSING_LANGUAGE_MAPPING,
				],
				true
			);
	}

	public static function spendCapForState() {
		$spendCap = SpendCapState::getShown();

		return $spendCap ? $spendCap->toArray() : [];
	}

	private function isOutOfCredit( $job ) {
		return WPML_TM_ATE_API::NOT_ENOUGH_CREDIT_STATUS === (int) Obj::prop( 'ateStatus', $job );
	}

	private function isWaitingForPayment( $job ) {
		return WPML_TM_ATE_API::WAITING_AUTOMATIC_PAYMENT_STATUS === (int) Obj::prop( 'ateStatus', $job );
	}

	private function finish( $reason, array $applied, $stillInProgress, $pendingAfter ) {
		if ( $applied ) {
			do_action( 'wpml_tm_ate_jobs_downloaded', wpml_collect( $applied ) );
		}

		if ( self::EXIT_LOCK_BUSY !== $reason ) {
			$pendingAfter = null === $pendingAfter ? $this->pendingJobs->summary() : $pendingAfter;

			if ( $this->reported ) {
				if ( ! empty( $this->reported['insufficient_balance_ate_job_ids'] ) ) {
					$credits = \WPML\TM\API\ATE\Account::getCredits( true )->getOrElse( null );
					if ( is_array( $credits ) ) {
						SpendCapState::inferFromParkedJobs( $credits, $this->reported['insufficient_balance_ate_job_ids'] );
					}
				}

				State::update(
					array_merge(
						$this->reported,
						[
							'client_restricted'         => ClientRestrictionState::isRestricted(),
							'client_restriction_debtor' => ClientRestrictionState::debtorBlock(),
							'spend_cap'                 => self::spendCapForState(),
							'ate_signals_at'            => time(),
						]
					)
				);
			}

			State::update( [ 'last_applied_jobs' => $this->appliedJobsForTheUi( $applied ) ] );

			State::onCollectionResult(
				count( $applied ),
				$pendingAfter['count'],
				$stillInProgress,
				$pendingAfter['oldest_at'],
				isset( $pendingAfter['oldest_automatic_at'] ) ? $pendingAfter['oldest_automatic_at'] : 0,
				self::EXIT_ATE_TIMEOUT === $reason,
				self::EXIT_ATE_REFUSED_LOCALLY === $reason,
				$this->leavesMoreToWrite( $reason, count( $applied ), (int) $pendingAfter['count'] ),
				(int) round( 1000 * ( $this->appliesInRun > 0 ? $this->slowestApply : $this->slowestApplyEstimate ) )
			);
		} else {
			$pendingAfter = [ 'count' => (int) State::getKey( 'pending_count', 0 ), 'oldest_at' => 0 ];
		}

		JobLog::add(
			'pull_collect_exit',
			[
				'reason'            => $reason,
				'applied'           => count( $applied ),
				'pending'           => $pendingAfter['count'],
				'still_in_progress' => $stillInProgress,
				'cost'              => $this->meter->stop(),
			]
		);

		return [
			'reason'            => $reason,
			'applied'           => count( $applied ),
			'pending'           => (int) $pendingAfter['count'],
			'still_in_progress' => (int) $stillInProgress,
		];
	}

	private function leavesMoreToWrite( $reason, $appliedCount, $pendingCount ) {
		if ( self::EXIT_ATE_TIMEOUT === $reason || self::EXIT_ATE_REFUSED_LOCALLY === $reason || self::EXIT_ERROR === $reason ) {
			return false;
		}

		if ( $pendingCount < 1 ) {
			return false;
		}

		return $appliedCount > 0 || ReadyQueue::count() > 0;
	}

	private function appliedJobsForTheUi( array $applied ) {
		$jobs = [];

		foreach ( $applied as $job ) {
			$jobId  = (int) Obj::prop( 'jobId', $job );
			$jobs[] = [
				'jobId'     => $jobId,
				'automatic' => (bool) Obj::propOr( false, 'automatic', $job ),
				'term'      => JobKind::isTaxonomyTerm( $jobId ),
			];
		}

		return $jobs;
	}
}
