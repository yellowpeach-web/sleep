<?php

namespace WPML\TM\ATE\Handshake;

use WPML\TM\ATE\Cancel\FinishedAtAte;
use WPML\TM\ATE\Cancel\PendingCancels;
use WPML\TM\ATE\CollectLock;
use WPML\TM\ATE\Log\HostLog;
use WPML\TM\ATE\PullDelivery\Cadence;
use WPML\TM\ATE\PullDelivery\PendingJobs;
use WPML\TM\ATE\PullDelivery\State as PullState;
use WPML\TM\ATE\Receive\SingleJobDelivery;
use WPML\TM\ATE\Sync\SyncChunk;
use WPML\TM\Jobs\JobLog;

class Runner {

	const TRIGGER_WAKE_BACKGROUND = 'wake_background';
	const TRIGGER_WAKE_SPAWNED    = 'wake_spawned';
	const TRIGGER_DRIVE           = 'drive';
	const TRIGGER_ADMIN           = 'admin';
	const TRIGGER_CRON            = 'cron';
	const TRIGGER_FRONT           = 'front';
	const TRIGGER_MANUAL          = 'manual';
	const TRIGGER_DASHBOARD       = 'dashboard';

	const TRIGGERS = [
		self::TRIGGER_WAKE_BACKGROUND,
		self::TRIGGER_WAKE_SPAWNED,
		self::TRIGGER_DRIVE,
		self::TRIGGER_ADMIN,
		self::TRIGGER_CRON,
		self::TRIGGER_FRONT,
		self::TRIGGER_MANUAL,
		self::TRIGGER_DASHBOARD,
	];

	const READY_STATUSES = [
		\WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_TRANSLATED,
		\WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_DELIVERING,
		\WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_EDITED,
	];

	const CLOSURE_FAILED_ON_SITE = 'failed_on_site';
	const CLOSURE_NOT_NEEDED     = 'not_needed_by_site';
	const CLOSURE_DECLINED       = 'declined';

	const POST_RESPONSE_PRIORITY = 2;

	const REARM_LOOPBACK = 'loopback';
	const REARM_CRON     = 'cron';
	const REARM_NONE     = 'none';

	public static $jobLog = true;

	private $lock;

	private $ate;

	private $delivery;

	private $decline;

	private $marks;

	private $pending;

	private $budget;

	private $maxJobs;

	private $counts = [];

	private $reported = [];

	private $written = [];

	private $creditResume;

	private $deadline = 0.0;

	const BACKOFF_TRIGGERS = [ self::TRIGGER_ADMIN, self::TRIGGER_CRON, self::TRIGGER_FRONT, self::TRIGGER_DASHBOARD ];

	private $manualBackoff;

	private $leftOutBackedOff = false;

	private $trigger = '';

	private $pendingUnnamed;

	private $unnamed = [];

	private $listedAteIds = [];
	private $startedAt = 0.0;

	private $inFlightIds = null;

	private $decidedAteIds = [];

	private static $startedForOwed = false;

	private $replayOwed;

	public function __construct(
		?CollectLock $lock = null,
		?AteClient $ate = null,
		?Delivery $delivery = null,
		?Decline $decline = null,
		?Marks $marks = null,
		?PendingJobs $pending = null,
		$budget = null,
		$maxJobs = null,
		?CreditResume $creditResume = null,
		?\WPML\TM\ATE\Sync\ManualSyncBackoff $manualBackoff = null,
		?PendingUnnamed $pendingUnnamed = null,
		$replayOwed = null
	) {
		$this->replayOwed    = $replayOwed ?: [ PendingCancels::class, 'replayAfterRun' ];
		$this->manualBackoff = $manualBackoff ?: new \WPML\TM\ATE\Sync\ManualSyncBackoff();
		$this->creditResume = $creditResume ?: new CreditResume();
		$this->lock     = $lock ?: new CollectLock();
		$this->ate      = $ate ?: new AteClient();
		$this->delivery = $delivery ?: new Delivery();
		$this->marks    = $marks ?: new Marks();
		$this->decline  = $decline ?: new Decline( $this->ate, $this->marks );
		$this->pending  = $pending ?: new PendingJobs();
		$this->budget   = $budget ?: [ Cadence::class, 'collectBudgetSeconds' ];
		$this->maxJobs  = $maxJobs ?: [ Cadence::class, 'maxJobsPerRun' ];
		$this->pendingUnnamed = $pendingUnnamed ?: new PendingUnnamed( $this->marks, $this->decline );
	}

	public static function spawn( $trigger ) {
		$plan = Plan::decide();

		if ( Plan::BACKGROUND === $plan ) {
			self::runAfterResponse( $trigger );

			return Plan::BACKGROUND;
		}

		if ( Plan::SPAWNED === $plan ) {
			self::requestCollect( $trigger );

			return Plan::SPAWNED;
		}

		( new self() )->run( $trigger );

		return 'inline';
	}

	public static function startForOwed() {
		if ( self::$startedForOwed ) {
			return;
		}
		self::$startedForOwed = true;

		if ( Plan::phpFinishesEarly() ) {
			self::runAfterResponse( self::TRIGGER_ADMIN );
		} elseif ( true === PullState::getKey( 'loopback_ok' ) ) {
			self::requestCollect( self::TRIGGER_ADMIN );
		} else {
			CronTick::openForWorkLeft();
		}
	}

	public static function runAfterResponse( $trigger ) {
		add_action(
			'shutdown',
			function () use ( $trigger ) {
				if ( function_exists( 'fastcgi_finish_request' ) ) {
					if ( function_exists( 'ignore_user_abort' ) ) {
						ignore_user_abort( true );
					}

					fastcgi_finish_request();

					PendingCancels::answerSent();
				}

				( new self() )->run( $trigger );
			},
			self::POST_RESPONSE_PRIORITY
		);
	}

	public static function requestCollect( $trigger, $recordSpawn = true ) {
		$changes = $recordSpawn ? State::spawnChanges() : [];

		if ( $changes ) {
			State::update( $changes );
		}

		wp_remote_post(
			Routes::url( Routes::COLLECT ),
			[
				'timeout'    => 1,
				'blocking'   => false,
				'sslverify'  => false,
				'user-agent' => Plan::LOOPBACK_USER_AGENT,
				'body'       => [
					'token'   => CollectToken::mint(),
					'trigger' => (string) $trigger,
				],
			]
		);
	}

	public function run( $trigger ) {
		$trigger      = in_array( $trigger, self::TRIGGERS, true ) ? $trigger : self::TRIGGER_MANUAL;
		$this->counts = self::emptyCounts();

		$key = $this->lock->create();

		if ( ! $key ) {
			$this->counts['lock_busy'] = true;
			Log::write( 'run_start', [ 'trigger' => $trigger, 'lock_busy' => true ] );

			return $this->counts;
		}

		$requestId                  = Declaration::begin();
		$this->counts['request_id'] = $requestId;
		$this->trigger              = $trigger;
		$this->leftOutBackedOff     = $this->leavesOutBackedOff( $trigger );
		$this->reported             = [];
		$this->written              = [];
		$this->unnamed              = [];
		$this->listedAteIds         = [];
		$this->startedAt            = Clock::exact();
		$this->inFlightIds          = null;
		$this->decidedAteIds        = [];
		Reports::setInRun( true );
		$ownsJobLog                 = $this->openJobLog( $trigger );

		try {
			Log::write( 'run_start', [ 'trigger' => $trigger ] );
			State::update(
				[
					'last_run_at'      => Clock::now(),
					'last_run_trigger' => $trigger,
					'spawned_at'       => 0,
				]
			);

			$this->deadline = Clock::exact() + max( 1, (int) call_user_func( $this->budget ) );
			$this->collect( (string) $key, $this->deadline );
		} catch ( \Throwable $e ) {
			$this->counts['error'] = $e->getMessage();
			HostLog::caught( 'handshake-run', $e );
		} finally {
			Declaration::end();
			$this->lock->release();
			$this->closeJobLog( $ownsJobLog );
		}

		try {
			return $this->finish( $trigger );
		} finally {
			Reports::setInRun( false );
		}
	}

	private function collect( $key, $deadline ) {
		$inFlight  = $this->ate->inFlight( $this->leftOutBackedOff );
		$ateJobIds = isset( $inFlight['ateJobIds'] ) ? array_values( array_map( 'intval', $inFlight['ateJobIds'] ) ) : [];
		$this->inFlightIds = $ateJobIds;
		$ateJobIds = array_values( array_unique( array_merge( $this->markedAteIds(), $ateJobIds ) ) );
		$chunks    = array_chunk( $ateJobIds, max( 1, self::chunkSize() ) );
		$maxJobs   = max( 1, (int) call_user_func( $this->maxJobs ) );
		$listedAll = true;

		foreach ( $chunks as $index => $chunk ) {
			if ( Clock::exact() >= $deadline || $this->processed() >= $maxJobs ) {
				$this->counts['budget_hit'] = true;
				$this->counts['work_left']  = true;
				$listedAll                  = false;
				break;
			}

			$answer = $this->ate->syncAll( $chunk, $inFlight, count( $ateJobIds ) );

			$this->lock->create( $key );

			if ( is_wp_error( $answer ) || ! is_object( $answer ) ) {
				$this->counts['ate_error'] = is_wp_error( $answer ) ? (string) $answer->get_error_code() : 'no_answer';
				$this->countError( 'sync' );
				$listedAll                 = false;
				break;
			}

			$this->counts['listed'] += count( $chunk );
			$this->listedAteIds     += array_fill_keys( $chunk, true );

			$ready = self::oldestJobFirst( $this->readAnswer( $answer ) );

			foreach ( $ready as $position => $item ) {
				if ( Clock::exact() >= $deadline || $this->processed() >= $maxJobs ) {
					$this->counts['budget_hit'] = true;
					$this->counts['work_left']  = true;
					$listedAll                  = $listedAll && ( count( $chunks ) - 1 === $index );
					break 2;
				}

				$this->writeOne( $item );
			}
		}

		if ( $listedAll ) {
			$this->takePendingUnnamed( $deadline, $maxJobs );
			$this->handReported();
			$this->markManualSlotUsed();
		}
	}

	private function takePendingUnnamed( $deadline, $maxJobs ) {
		$items = array_diff_key( $this->unnamed, $this->listedAteIds );

		if ( ! $items ) {
			return;
		}

		if ( $this->pendingUnnamed->inEpisode() ) {
			$this->counts['unnamed_left'] += count( $items );
			Log::write( 'pending_unnamed', [ 'action' => PendingUnnamed::ACTION_LEAVE, 'reason' => PendingUnnamed::LEFT_EPISODE, 'items' => count( $items ) ] );

			return;
		}

		foreach ( $items as $item ) {
			if ( Clock::exact() >= $deadline || $this->processed() >= $maxJobs ) {
				$this->counts['unnamed_budget_hit'] = true;

				return;
			}

			$this->counts['unnamed_examined']++;
			$decision = $this->pendingUnnamed->decide( $item );

			if ( PendingUnnamed::ACTION_LEAVE === $decision['action'] ) {
				$this->counts['unnamed_left']++;
				Log::write( 'pending_unnamed', [ 'action' => $decision['action'], 'reason' => $decision['reason'], 'rid' => $item['rid'] ], $decision['job_id'] ?: null, $item['ate_id'] );

				continue;
			}

			$ok = $this->pendingUnnamed->act( $item, $decision );

			if ( PendingUnnamed::ACTION_DECLINE === $decision['action'] ) {
				$this->counts[ $ok ? 'declined' : 'decline_failed' ]++;
				$this->counts[ $ok ? 'unnamed_declined' : 'unnamed_decline_failed' ]++;
			} elseif ( PendingUnnamed::ACTION_CONFIRM === $decision['action'] && $ok ) {
				$this->counts['unnamed_confirmed']++;
			} elseif ( PendingUnnamed::ACTION_ADOPT === $decision['action'] && $ok ) {
				$this->counts['unnamed_adopted']++;
			}
		}
	}

	private function leavesOutBackedOff( $trigger ) {
		if ( ! in_array( $trigger, self::BACKOFF_TRIGGERS, true ) ) {
			return false;
		}

		return ! $this->manualBackoff->isDue();
	}

	private function markManualSlotUsed() {
		if ( ! $this->leftOutBackedOff && in_array( $this->trigger, self::BACKOFF_TRIGGERS, true ) ) {
			$this->manualBackoff->markSynced();
		}
	}

	private function readAnswer( $answer ) {
		$ready = [];
		$items = isset( $answer->items ) && is_array( $answer->items ) ? $answer->items : [];

		$this->counts['named'] += count( $items );
		$jobIds = $this->localJobIds( $items );
		$marks  = $jobIds ? $this->marks->marksOf( array_values( array_filter( $jobIds ) ) ) : [];

		foreach ( $items as $index => $item ) {
			if ( ! is_object( $item ) ) {
				continue;
			}

			$ateJobId = isset( $item->ate_id ) ? (int) $item->ate_id : 0;
			$rid      = isset( $item->id ) ? (int) $item->id : 0;
			$status   = isset( $item->status ) ? (int) $item->status : -1;
			$closure  = isset( $item->closure ) && is_string( $item->closure ) ? $item->closure : '';
			$jobId    = isset( $jobIds[ $index ] ) ? $jobIds[ $index ] : 0;
			$mark     = self::markOn( $marks, $jobId, $ateJobId );

			if ( null !== $mark && Marks::UNCONFIRMED === $mark['mark'] ) {
				if ( '' === $closure && in_array( $status, self::READY_STATUSES, true ) ) {
					$ready[] = [
						'ate_job_id'  => $ateJobId,
						'rid'         => $rid,
						'job_id'      => $jobId,
						'unconfirmed' => true,
					];
				} else {
					$this->clearUnconfirmed( $jobId, $ateJobId, '' !== $closure ? [ 'closure' => $closure ] : [ 'status' => $status ] );
				}
				continue;
			}

			if ( '' !== $closure ) {
				if ( null === $mark || ! self::markIsFor( $mark['mark'], $closure ) ) {
					$this->takeClosure( $jobId, $ateJobId, $closure );
				}
				continue;
			}

			if ( in_array( $status, \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUSES_CANCELLED, true ) && $jobId > 0 ) {
				$this->decidedAteIds[ $ateJobId ] = true;
				$this->delivery->reflectCancelled( $jobId, $ateJobId );
				$this->counts['cancelled_by_ate']++;
			}

			if ( \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_CANCELED_BY_USER_SHOULD_HIDE === $status ) {
				if ( $jobId > 0 ) {
					$this->delivery->clearError( $jobId );
				}
				continue;
			}

			if ( \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_CANCELLED === $status ) {
				continue;
			}

			$unsolvable = ! empty( $item->is_unsolvable )
				|| in_array( $status, [ \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_FAILED, \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_MISSING_LANGUAGE_MAPPING ], true );

			if ( $unsolvable ) {
				$this->report( 'unsolvable_job_ids', $jobId );

				if ( $jobId > 0 ) {
					$this->delivery->recordUnsolvable( $jobId, $ateJobId, $status, isset( $item->message ) && is_string( $item->message ) ? $item->message : '' );
				}
			} elseif ( \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_DELIVERED === $status && ! ( $jobId > 0 && $this->delivery->isTermBundle( $jobId ) ) ) {
				if ( $jobId > 0 && $this->delivery->isTaxonomyTerm( $jobId ) && $this->delivery->completeDeliveredTermJob( $jobId ) ) {
					$this->counts['terms_completed']++;
				}
			} elseif ( \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_NOT_ENOUGH === $status ) {
				$this->report( 'insufficient_balance_job_ids', $jobId );
				$this->report( 'insufficient_balance_ate_job_ids', $ateJobId );
			} elseif ( \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_WAITING_AUTOMATIC_PAYMENT === $status ) {
				$this->report( 'payment_pending_job_ids', $jobId );
				$this->report( 'payment_pending_ate_job_ids', $ateJobId );
			} elseif ( ( in_array( $status, self::READY_STATUSES, true ) || \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_DELIVERED === $status ) && $jobId > 0 ) {
				if ( null !== $mark && ! $this->takeReopened( $jobId, $ateJobId, $mark, self::reopenedAt( $item ) ) ) {
					continue;
				}

				$ready[] = [
					'ate_job_id' => $ateJobId,
					'rid'        => $rid,
					'job_id'     => $jobId,
				];
			}
		}

		$this->counts['ready'] += count( $ready );

		$unknown = isset( $answer->unknown_ids ) && is_array( $answer->unknown_ids ) ? $answer->unknown_ids : [];

		foreach ( $unknown as $ateJobId ) {
			$this->takeUnknown( (int) $ateJobId );
		}

		$this->unnamed += PendingUnnamed::itemsOf( $answer );

		return $ready;
	}

	private function takeClosure( $jobId, $ateJobId, $closure ) {
		$this->decidedAteIds[ (int) $ateJobId ] = true;

		$mark = self::markForClosure( $closure );

		if ( null === $mark || $jobId < 1 ) {
			Log::write( 'closure_seen', [ 'closure' => $closure, 'taken' => false ], $jobId ?: null, $ateJobId );

			return;
		}

		$this->marks->set( $jobId, $ateJobId, $mark );

		if ( Marks::NEEDS_RESEND === $mark ) {
			$this->delivery->giveUpForResend( $jobId, $ateJobId, Delivery::RESEND_CLOSED_NOT_NEEDED );
		} else {
			$this->delivery->removeFromInFlight( $jobId, $ateJobId );
		}

		$this->counts['closures']++;

		Log::write( 'closure_seen', [ 'closure' => $closure, 'taken' => true ], $jobId, $ateJobId );
	}

	private function markedAteIds() {
		$ids = [];

		foreach ( [ Marks::FAILED_ON_SITE, Marks::UNCONFIRMED ] as $mark ) {
			foreach ( $this->marks->jobsMarked( $mark ) as $job ) {
				if ( (int) $job['ate_job_id'] > 0 ) {
					$ids[] = (int) $job['ate_job_id'];
				}
			}
		}

		return $ids;
	}

	private function markUnconfirmed( $jobId, $ateJobId, $outcome ) {
		$this->marks->set( $jobId, $ateJobId, Marks::UNCONFIRMED );
		$this->counts['unconfirmed']++;

		Log::write( 'confirm_failed', [ 'outcome' => (string) $outcome, 'mark' => Marks::UNCONFIRMED ], $jobId, $ateJobId );
	}

	private function clearUnconfirmed( $jobId, $ateJobId, array $because ) {
		$this->marks->clear( $jobId );
		$this->counts['unconfirmed_cleared']++;

		Log::write( 'unconfirmed_cleared', $because, $jobId, $ateJobId );
	}

	private function wroteWithoutConfirm( $jobId ) {
		return SingleJobDelivery::ALREADY_APPLIED === $this->delivery->refusal( $jobId );
	}

	private function localJobIds( array $items ) {
		$jobIds = [];

		foreach ( $items as $index => $item ) {
			if ( is_object( $item ) ) {
				$jobIds[ $index ] = $this->delivery->localJobId(
					isset( $item->ate_id ) ? (int) $item->ate_id : 0,
					isset( $item->id ) ? (int) $item->id : 0
				);
			}
		}

		return $jobIds;
	}

	private static function markOn( array $marks, $jobId, $ateJobId ) {
		if ( $jobId < 1 || ! isset( $marks[ $jobId ] ) ) {
			return null;
		}

		return (int) $marks[ $jobId ]['ate_job_id'] === (int) $ateJobId ? $marks[ $jobId ] : null;
	}

	private static function markIsFor( $mark, $closure ) {
		return self::markForClosure( $closure ) === $mark;
	}

	private static function markForClosure( $closure ) {
		$byClosure = [
			self::CLOSURE_FAILED_ON_SITE => Marks::FAILED_ON_SITE,
			self::CLOSURE_NOT_NEEDED     => Marks::NEEDS_RESEND,
			self::CLOSURE_DECLINED       => Marks::DECLINED,
		];

		return $byClosure[ $closure ] ?? null;
	}

	private function takeReopened( $jobId, $ateJobId, array $mark, $reopenedAt ) {
		if ( Marks::FAILED_ON_SITE !== $mark['mark'] || $reopenedAt < 1 || $reopenedAt <= (int) $mark['marked_at'] ) {
			return false;
		}

		$this->delivery->takeBackReopened( $this->marks, $jobId );
		$this->counts['reopened']++;

		Log::write(
			'mark_cleared_by_reopen',
			[
				'mark'        => $mark['mark'],
				'marked_at'   => (int) $mark['marked_at'],
				'reopened_at' => (int) $reopenedAt,
			],
			$jobId,
			$ateJobId
		);

		return true;
	}

	private static function reopenedAt( $item ) {
		if ( ! isset( $item->reopened_at ) ) {
			return 0;
		}

		$value = $item->reopened_at;

		if ( is_int( $value ) || is_float( $value ) || ( is_string( $value ) && is_numeric( $value ) ) ) {
			$seconds = (float) $value;

			return (int) ( $seconds > 99999999999 ? floor( $seconds / 1000 ) : floor( $seconds ) );
		}

		if ( ! is_string( $value ) || '' === $value ) {
			return 0;
		}

		$time = strtotime( $value );

		return false === $time ? 0 : (int) $time;
	}

	private function takeUnknown( $ateJobId ) {
		$this->decidedAteIds[ (int) $ateJobId ] = true;

		$jobId = $this->delivery->localJobId( $ateJobId );

		if ( $jobId > 0 && null !== $this->unconfirmedMarkOn( $jobId, $ateJobId ) ) {
			$this->clearUnconfirmed( $jobId, $ateJobId, [ 'unknown' => true ] );

			return;
		}

		if ( $jobId > 0 ) {
			$this->marks->set( $jobId, $ateJobId, Marks::NEEDS_RESEND );
			$this->delivery->giveUpForResend( $jobId, $ateJobId );
		}

		$this->counts['unknown']++;

		Log::write( 'unknown_seen', [ 'taken' => $jobId > 0 ], $jobId ?: null, $ateJobId );
	}

	private function unconfirmedMarkOn( $jobId, $ateJobId ) {
		$mark = self::markOn( $this->marks->marksOf( [ $jobId ] ), $jobId, $ateJobId );

		return null !== $mark && Marks::UNCONFIRMED === $mark['mark'] ? $mark : null;
	}

	private function writeOne( array $item ) {
		$jobId    = $item['job_id'];
		$ateJobId = $item['ate_job_id'];

		$this->decidedAteIds[ (int) $ateJobId ] = true;

		if ( ! empty( $item['unconfirmed'] ) ) {
			$this->confirmEarlierWrite( $jobId, $ateJobId, true );

			return;
		}

		$refusal = $this->delivery->refusal( $jobId );

		if ( SingleJobDelivery::CANCELLED === $refusal && $this->finishedBeforeItsOwedCancel( $jobId, $ateJobId ) ) {
			$refusal = $this->delivery->refusal( $jobId );
		}

		if ( SingleJobDelivery::ALREADY_APPLIED === $refusal ) {
			$this->confirmEarlierWrite( $jobId, $ateJobId );

			return;
		}

		if ( null !== $refusal ) {
			$this->declineRefusal( $jobId, $ateJobId, $refusal );

			return;
		}

		$syncNamedJobId = $this->delivery->jobIdOfRid( $item['rid'] );

		if ( $this->delivery->namesAnotherOriginal( $jobId, $syncNamedJobId ) ) {
			$this->delivery->recordWrongOriginal( $jobId, $ateJobId, $syncNamedJobId );
			$this->declineRefusal( $jobId, $ateJobId, SingleJobDelivery::WRONG_ORIGINAL );

			return;
		}

		$this->counts['fetched']++;
		Log::write( 'fetch', [], $jobId, $ateJobId );

		try {
			$outcome = $this->delivery->deliver( $jobId );
		} catch ( \Throwable $e ) {
			Log::write(
				'fetch',
				[
					'outcome' => 'error',
					'message' => $e->getMessage(),
					'class'   => get_class( $e ),
					'origin'  => HostLog::origin( $e ),
				],
				$jobId,
				$ateJobId
			);
			HostLog::caught( 'handshake', $e, $jobId, $ateJobId );
			$this->countError( self::errorClassOf( $e ) );

			if ( $this->delivery->isGivenUp( $jobId ) && $this->wasDeclinedByWrite( $jobId ) ) {
				$this->counts['declined']++;
			} elseif ( $this->wroteWithoutConfirm( $jobId ) ) {
				$this->countWrittenUnconfirmed( $jobId, $ateJobId, 'error' );
			} elseif ( self::isApplyFailure( $e ) && $this->delivery->isAutomatic( $jobId ) ) {
				$this->declineFailedFetch( $jobId, $ateJobId, $e->getMessage() );
			}

			return;
		}

		if ( SingleJobDelivery::APPLIED === $outcome ) {
			if ( $this->delivery->isGivenUp( $jobId ) && $this->wasDeclinedByWrite( $jobId ) ) {
				$this->counts['declined']++;

				return;
			}

			$this->counts['written']++;
			$this->counts['confirmed']++;
			$this->written[ $jobId ] = $ateJobId;
			Log::write( 'written', [], $jobId, $ateJobId );
			Log::write( 'confirmed', [], $jobId, $ateJobId );

			return;
		}

		if ( SingleJobDelivery::ALREADY_APPLIED === $outcome ) {
			$this->confirmEarlierWrite( $jobId, $ateJobId );

			return;
		}

		if ( null !== Decline::reasonFor( $outcome ) ) {
			$this->declineRefusal( $jobId, $ateJobId, $outcome );

			return;
		}

		if ( SingleJobDelivery::APPLY_FAILED === $outcome && $this->wroteWithoutConfirm( $jobId ) ) {
			$this->countWrittenUnconfirmed( $jobId, $ateJobId, $outcome );

			return;
		}

		Log::write( 'fetch', [ 'outcome' => $outcome ], $jobId, $ateJobId );

		if ( SingleJobDelivery::APPLY_FAILED === $outcome ) {
			$this->countError( 'apply' );
		} elseif ( SingleJobDelivery::LOCK_BUSY === $outcome ) {
			$this->countError( 'lock' );
		}

		if ( SingleJobDelivery::APPLY_FAILED === $outcome && $this->delivery->isAutomatic( $jobId ) ) {
			$this->declineFailedFetch( $jobId, $ateJobId, \WPML_TM_ATE_Jobs::lastUnsavedReason() );
		}

	}

	private function finishedBeforeItsOwedCancel( $jobId, $ateJobId ) {
		if ( ! PendingCancels::isOwedForRun( (int) $ateJobId ) ) {
			return false;
		}

		$putBack = FinishedAtAte::putBack( [ (int) $ateJobId ] );
		if ( ! $putBack ) {
			return false;
		}

		PendingCancels::forget( [ (int) $ateJobId ] );
		Log::write( 'finished_before_owed_cancel', [ 'put_back' => true ], $jobId, $ateJobId );
		JobLog::add( 'tea_cancel_all_already_translated_in_ate', [
			'ate_job_ids'  => [ (int) $ateJobId ],
			'put_back'     => [ (int) $ateJobId ],
			'wpml_job_ids' => array_keys( $putBack ),
		] );

		return true;
	}

	private static function isApplyFailure( \Throwable $e ) {
		return ! $e instanceof \WPML\TM\ATE\API\RequestException;
	}

	private static function errorClassOf( \Throwable $e ) {
		if ( self::isApplyFailure( $e ) ) {
			return 'apply';
		}

		return 'class-wpml-tm-ate-jobs.php' === basename( (string) $e->getFile() ) ? 'parse' : 'download';
	}

	private function countError( $class ) {
		$this->counts['errors'][ $class ] = ( isset( $this->counts['errors'][ $class ] ) ? (int) $this->counts['errors'][ $class ] : 0 ) + 1;
	}

	private function declineFailedFetch( $jobId, $ateJobId, $reason ) {
		if ( ! $this->decline->send( $ateJobId, $jobId, Decline::APPLY_FAILED ) ) {
			$this->counts['decline_failed']++;

			return;
		}

		$this->counts['declined']++;

		if ( $this->delivery->holdsDeliveredTranslation( $jobId ) ) {
			$this->delivery->removeFromInFlight( $jobId, $ateJobId );

			return;
		}

		$this->delivery->giveUpFailedFetch( $jobId, $ateJobId, (string) $reason );
	}

	private function countWrittenUnconfirmed( $jobId, $ateJobId, $outcome ) {
		$this->counts['written']++;
		$this->written[ $jobId ] = $ateJobId;
		Log::write( 'written', [ 'confirmed' => false ], $jobId, $ateJobId );
		$this->markUnconfirmed( $jobId, $ateJobId, $outcome );
	}

	private function wasDeclinedByWrite( $jobId ) {
		$marks = $this->marks->marksOf( [ $jobId ] );

		return Marks::DECLINED === ( isset( $marks[ $jobId ]['mark'] ) ? $marks[ $jobId ]['mark'] : null );
	}

	private function confirmEarlierWrite( $jobId, $ateJobId, $marked = false ) {
		$confirmed = $this->delivery->confirmApplied( $jobId, $ateJobId );

		$this->counts['confirmed_earlier']++;

		if ( $confirmed ) {
			$this->counts['confirmed']++;
		}

		Log::write( 'confirmed', [ 'already_applied' => true, 'ok' => $confirmed ], $jobId, $ateJobId );

		if ( $confirmed && $marked ) {
			$this->clearUnconfirmed( $jobId, $ateJobId, [ 'confirmed' => true ] );
		} elseif ( ! $confirmed && ! $marked ) {
			$this->markUnconfirmed( $jobId, $ateJobId, 'confirm_refused' );
		}
	}

	private function declineRefusal( $jobId, $ateJobId, $outcome ) {
		$reason = Decline::reasonFor( $outcome );

		if ( null === $reason ) {
			return;
		}

		if ( $this->decline->send( $ateJobId, $jobId, $reason ) ) {
			$this->counts['declined']++;
		} else {
			$this->counts['decline_failed']++;
		}
	}

	private static function oldestJobFirst( array $ready ) {
		$keyed = [];

		foreach ( array_values( $ready ) as $index => $item ) {
			$keyed[] = [ (int) $item['job_id'], $index, $item ];
		}

		usort(
			$keyed,
			function ( $a, $b ) {
				return [ $a[0], $a[1] ] <=> [ $b[0], $b[1] ];
			}
		);

		return array_column( $keyed, 2 );
	}

	private function report( $key, $id ) {
		if ( $id > 0 ) {
			$this->reported[ $key ][] = (int) $id;
		}
	}

	private function handReported() {
		$lists = [
			'unsolvable_job_ids'               => [],
			'insufficient_balance_job_ids'     => [],
			'insufficient_balance_ate_job_ids' => [],
			'payment_pending_job_ids'          => [],
			'payment_pending_ate_job_ids'      => [],
		];
		$lists = array_merge( $lists, $this->reported );

		$this->putFirstParkedSetOnFile( $lists );

		$this->inferSpendCap( $lists['insufficient_balance_ate_job_ids'] );

		$state = PullState::getFresh();

		foreach ( [ 'insufficient_balance' => 'insufficient_balance_ate_job_ids', 'payment_pending' => 'payment_pending_ate_job_ids' ] as $prefix => $key ) {
			$before = (int) $state[ $prefix . '_since' ];
			$listed = ! empty( $lists[ $key ] );

			$lists[ $prefix . '_since' ] = $listed ? ( $before > 0 && ! empty( $state[ $key ] ) ? $before : Clock::now() ) : 0;
		}

		PullState::update( $lists, $state );
	}

	private function putFirstParkedSetOnFile( array $lists ) {
		if ( ! $lists['insufficient_balance_ate_job_ids'] || PullState::getFresh()['insufficient_balance_ate_job_ids'] ) {
			return;
		}

		PullState::update(
			[
				'insufficient_balance_job_ids'     => $lists['insufficient_balance_job_ids'],
				'insufficient_balance_ate_job_ids' => $lists['insufficient_balance_ate_job_ids'],
			]
		);
	}

	private function inferSpendCap( array $parkedAteIds ) {
		if ( ! $parkedAteIds ) {
			return;
		}

		try {
			$credits = $this->readCredits();
		} catch ( \Throwable $e ) {
			$credits = null;
		}

		\WPML\TM\ATE\API\SpendCapState::inferFromParkedJobs( is_array( $credits ) ? $credits : [], $parkedAteIds );
	}

	protected function readCredits() {
		return \WPML\TM\API\ATE\Account::getCredits( true )->getOrElse( null );
	}

	private function processed() {
		return $this->counts['fetched'] + $this->counts['declined'] + $this->counts['decline_failed'] + $this->counts['confirmed_earlier']
			+ $this->counts['unnamed_examined'] - $this->counts['unnamed_declined'] - $this->counts['unnamed_decline_failed'];
	}

	private function finish( $trigger ) {
		$summary = $this->pending->summary();

		$this->counts['remaining'] = (int) $summary['count'];

		State::update(
			array_merge(
				State::inFlightSummaryChanges( $this->marks->withUnconfirmed( $summary ) ),
				[
					'last_run_at'        => Clock::now(),
					'last_run_trigger'   => $trigger,
					'last_run_work_left' => (bool) $this->counts['work_left'],
				]
			)
		);

		$this->delivery->announceWritten( $this->written );

		Log::write(
			'run_end',
			array_merge( [ 'trigger' => $trigger ], array_diff_key( $this->counts, [ 'request_id' => true ] ) ),
			null,
			null,
			$this->counts['request_id']
		);

		$this->resumeParked();
		$this->sendReports( $trigger );

		if ( $this->replayOwed( $trigger ) ) {
			$this->counts['work_left']  = true;
			$this->counts['budget_hit'] = true;
			State::update( [ 'last_run_work_left' => true ] );
		}

		if ( $this->counts['work_left'] && self::TRIGGER_DRIVE !== $trigger ) {
			$this->rearm( $trigger );
		} else {
			self::logRearm( self::REARM_NONE, $trigger, (bool) $this->counts['work_left'] );
		}

		return $this->counts;
	}

	private function sendReports( $trigger ) {
		try {
			if ( null !== $this->inFlightIds ) {
				$this->counts['released_unreported'] = Reports::trackInFlight( $this->inFlightIds, array_keys( $this->decidedAteIds ), $trigger );
			}

			Reports::add( Reports::RUN_SUMMARY, Reports::runSummary( $trigger, $this->counts, (int) round( ( Clock::exact() - $this->startedAt ) * 1000 ) ) );
			Reports::queueStateIfChanged();
			Reports::flush( $this->ate );
		} catch ( \Throwable $e ) {
			Log::write( 'run_end', [ 'reports_error' => $e->getMessage() ] );
		}
	}

	private function replayOwed( $trigger ) {
		try {
			$outcome = call_user_func( $this->replayOwed, $this->deadline );
		} catch ( \Throwable $e ) {
			Log::write( 'owed_replay', [ 'trigger' => $trigger, 'error' => get_class( $e ) ] );

			return false;
		}

		if ( ! is_array( $outcome ) || PendingCancels::RUN_REPLAY_NOTHING_OWED === ( $outcome['skipped'] ?? '' ) ) {
			return false;
		}

		Log::write(
			'owed_replay',
			[
				'trigger' => $trigger,
				'skipped' => (string) ( $outcome['skipped'] ?? '' ),
				'sent'    => array_values( (array) ( $outcome['sent'] ?? [] ) ),
				'kept'    => count( (array) ( $outcome['kept'] ?? [] ) ),
				'failed'  => array_values( (array) ( $outcome['failed'] ?? [] ) ),
				'budget_left' => ! empty( $outcome['budget_left'] ),
			]
		);

		if ( empty( $outcome['budget_left'] ) && ! empty( $outcome['kept'] ) ) {
			CronTick::reconcile();
		}

		return ! empty( $outcome['budget_left'] );
	}

	private function resumeParked() {
		if ( $this->counts['budget_hit'] || Clock::exact() >= $this->deadline ) {
			return;
		}

		$this->creditResume->run( false );
	}

	protected function rearm( $trigger ) {
		if ( ! $this->counts['work_left'] || ! $this->counts['budget_hit'] ) {
			self::logRearm( self::REARM_NONE, $trigger, (bool) $this->counts['work_left'] );

			return self::REARM_NONE;
		}

		Plan::reprobeIfSpawnUnanswered();

		$verdict = PullState::getKey( 'loopback_ok' );
		$probed  = false;

		if ( ! is_bool( $verdict ) ) {
			$verdict = (bool) $this->probeLoopback();
			$probed  = true;
		}

		if ( $verdict ) {
			$this->chainOverLoopback( self::TRIGGER_WAKE_BACKGROUND === $trigger ? self::TRIGGER_WAKE_SPAWNED : $trigger );
			self::logRearm( self::REARM_LOOPBACK, $trigger, true, [ 'probed' => $probed ] );

			return self::REARM_LOOPBACK;
		}

		$due = CronTick::openForWorkLeft();
		self::logRearm( self::REARM_CRON, $trigger, true, [ 'probed' => $probed, 'tick_due' => $due ] );

		return self::REARM_CRON;
	}

	protected function probeLoopback() {
		return Plan::loopbackVerdict();
	}

	protected function chainOverLoopback( $trigger ) {
		self::requestCollect( $trigger );
	}

	private static function logRearm( $branch, $trigger, $workLeft, array $extra = [] ) {
		Log::write(
			'rearm',
			array_merge(
				[
					'branch'    => $branch,
					'trigger'   => $trigger,
					'work_left' => (bool) $workLeft,
				],
				$extra
			)
		);
	}

	private function openJobLog( $trigger ) {
		if ( ! self::$jobLog ) {
			return false;
		}

		$owns = JobLog::maybeInitBackgroundRequest( 'wpml-ate-handshake' );
		JobLog::createNewGroup( JobLog::GROUP_ID_DOWNLOAD_JOBS, 'ATE level handshake run', [ 'trigger' => $trigger ] );

		return (bool) $owns;
	}

	private function closeJobLog( $owns ) {
		if ( ! self::$jobLog ) {
			return;
		}

		JobLog::finishCurrentGroup();

		if ( $owns ) {
			JobLog::flushBackgroundRequest();
		}
	}

	public static function chunkSize() {
		return SyncChunk::size();
	}

	private static function emptyCounts() {
		return [
			'lock_busy'           => false,
			'written'             => 0,
			'confirmed'           => 0,
			'confirmed_earlier'   => 0,
			'declined'            => 0,
			'decline_failed'      => 0,
			'fetched'             => 0,
			'listed'              => 0,
			'ready'               => 0,
			'unknown'             => 0,
			'closures'            => 0,
			'cancelled_by_ate'    => 0,
			'terms_completed'     => 0,
			'reopened'            => 0,
			'unconfirmed'         => 0,
			'unconfirmed_cleared' => 0,
			'unnamed_examined'       => 0,
			'unnamed_confirmed'      => 0,
			'unnamed_adopted'        => 0,
			'unnamed_declined'       => 0,
			'unnamed_decline_failed' => 0,
			'unnamed_left'           => 0,
			'unnamed_budget_hit'     => false,
			'named'               => 0,
			'errors'              => [ 'download' => 0, 'parse' => 0, 'apply' => 0, 'lock' => 0, 'sync' => 0 ],
			'budget_hit'          => false,
			'work_left'           => false,
			'remaining'           => 0,
			'request_id'          => '',
		];
	}
}
