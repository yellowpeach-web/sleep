<?php

namespace WPML\TM\ATE\PullDelivery;

use WPML\LIB\WP\Option;
use WPML\TM\Jobs\JobLog;

class State {

	const OPTION = 'wpml_pull_delivery_state';

	const RAISED_BY_WATCHDOG = 'watchdog';

	const RAISED_BY_PICK_UP = 'pick_up';

	public static function defaults() {
		return [
			'mode'               => Modes::PUSH_OK,
			'pending_count'      => 0,
			'still_in_progress'  => 0,
			'last_run_at'        => 0,
			'next_due_at'        => 0,
			'last_received_at'   => 0,
			'last_webhook_at'    => 0,
			'pacer_id'           => '',
			'pacer_lease_until'  => 0,
			'manual_force_until' => 0,
			'empty_runs'         => 0,
			'oldest_pending_at'  => 0,
			'oldest_automatic_pending_at' => 0,
			'suspect_since'      => 0,
			'suspect_raised_by'  => '',
			'suspect_settled_at' => 0,
			'watchdog_checked_at' => 0,
			'loopback_ok'        => null,
			'loopback_quick_ok'  => null,
			'reachability_asked_at' => 0,
			'collecting_since'              => 0,
			'job_error_revision'            => 0,
			'last_ate_ready_job_id'         => 0,
			'collection_ate_ready_job_id' => 0,
			'arranged_at'        => 0,
			'spawn_misses'       => 0,
			'draining'           => false,
			'slowest_apply_ms'   => 0,
			'client_restricted'  => false,
			'client_restriction_debtor' => '',
			'parked_since'       => 0,
			'payment_parked_ate_job_ids' => [],
			'spend_cap'          => [],
			'ate_signals_at'     => 0,
			'ate_signals_due_since' => 0,
			'insufficient_balance_job_ids' => [],
			'insufficient_balance_ate_job_ids' => [],
			'payment_pending_job_ids'     => [],
			'payment_pending_ate_job_ids' => [],
			'resume_attempted_for' => '',
			'resume_checked_at'  => 0,
			'resume_attempted_balance' => 0,
			'unsolvable_job_ids' => [],
			'eta_available'      => false,
			'eta_minutes'        => 0,
			'last_applied_jobs'  => [],
		];
	}

	public static function get() {
		$stored = Option::getOr( self::OPTION, [] );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		$state = array_merge( self::defaults(), array_intersect_key( $stored, self::defaults() ) );

		if ( ! Modes::isValid( $state['mode'] ) ) {
			$state['mode'] = Modes::PUSH_OK;
		}

		return $state;
	}

	public static function getFresh() {
		self::forgetCachedValue();

		return self::get();
	}

	public static function getKey( $key, $default = null ) {
		$state = self::get();

		return array_key_exists( $key, $state ) ? $state[ $key ] : $default;
	}

	public static function mode() {
		return self::get()['mode'];
	}

	public static function update( array $changes, ?array $state = null ) {
		$state = null === $state ? self::getFresh() : $state;
		$state = array_merge( $state, array_intersect_key( $changes, self::defaults() ) );

		if ( ! Modes::isValid( $state['mode'] ) ) {
			$state['mode'] = Modes::PUSH_OK;
		}


		Option::update( self::OPTION, $state );

		return $state;
	}

	public static function claimPacer( $tabId, $leaseUntil ) {
		$fresh = self::getFresh();

		if ( $fresh['pacer_lease_until'] > time() && $fresh['pacer_id'] !== $tabId ) {
			return [ 'claimed' => false, 'state' => $fresh ];
		}

		return [
			'claimed' => true,
			'state'   => self::update(
				[
					'pacer_id'          => $tabId,
					'pacer_lease_until' => $leaseUntil,
				],
				$fresh
			),
		];
	}

	private static function forgetCachedValue() {
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( self::OPTION, 'options' );
	}

	public static function reset() {
		Option::update( self::OPTION, self::defaults() );
	}


	public static function onWebhookReceived() {
		$now = time();

		return self::update(
			[
				'mode'              => Modes::PUSH_OK,
				'last_webhook_at'   => $now,
				'suspect_since'     => 0,
				'suspect_raised_by' => '',
				'empty_runs'        => 0,
				'next_due_at'       => 0,
				'manual_force_until' => 0,
				'pacer_id'          => '',
				'pacer_lease_until' => 0,
				'draining'          => false,
			]
		);
	}

	public static function onTranslationReceived() {
		$state   = self::getFresh();
		$pending = max( 0, (int) $state['pending_count'] - 1 );

		$changes = [
			'last_received_at' => time(),
			'pending_count'    => $pending,
		];

		if ( $pending < 1 ) {
			$changes['oldest_pending_at']           = 0;
			$changes['oldest_automatic_pending_at'] = 0;
			$changes['ate_signals_due_since'] = 0;
		}

		return self::update( $changes, $state );
	}

	public static function onJobsCancelled( ?array $pending = null ) {
		$state = self::getFresh();
		$count = max( 0, (int) ( isset( $pending['count'] ) ? $pending['count'] : 0 ) );

		$changes = [
			'pending_count'               => $count,
			'oldest_pending_at'           => $count > 0
				? max( 0, (int) ( isset( $pending['oldest_at'] ) ? $pending['oldest_at'] : 0 ) )
				: 0,
			'oldest_automatic_pending_at' => $count > 0
				? max( 0, (int) ( isset( $pending['oldest_automatic_at'] ) ? $pending['oldest_automatic_at'] : 0 ) )
				: 0,
		];

		if ( $count < 1 ) {
			$changes['ate_signals_due_since'] = 0;
		}

		return self::update( $changes, $state );
	}

	public static function onJobErrorChanged() {
		$state = self::getFresh();

		return self::update(
			[ 'job_error_revision' => max( 0, (int) $state['job_error_revision'] ) + 1 ],
			$state
		);
	}

	public static function onJobsCreated( $count = 1 ) {
		$count = max( 0, (int) $count );
		$state = self::getFresh();
		$now   = time();

		$changes = [
			'pending_count' => max( 0, (int) $state['pending_count'] ) + $count,
		];

		if ( ! $state['oldest_pending_at'] ) {
			$changes['oldest_pending_at'] = $now;
		}

		if ( Modes::isPull( $state['mode'] ) ) {
			$changes['mode']       = Modes::PULL_ACTIVE;
			$changes['empty_runs'] = 0;
			$changes['next_due_at'] = $now;

			if ( Modes::PULL_IDLE === $state['mode'] ) {
				$changes['pacer_id']          = '';
				$changes['pacer_lease_until'] = 0;
			}
		}

		return self::update( $changes, $state );
	}

	public static function onReturnDelivery( $ateJobId, $outcome, ?array $pending = null ) {
		$state    = self::getFresh();
		$now      = time();
		$ateJobId = max( 0, (int) $ateJobId );

		$changes = self::pendingReading( $pending, $state );

		if ( \WPML\TM\ATE\Receive\SingleJobDelivery::APPLIED === $outcome ) {
			$changes['last_received_at'] = $now;

			if ( $changes['pending_count'] < 1 ) {
				$changes['ate_signals_due_since'] = 0;

				if ( Modes::isPull( $state['mode'] ) ) {
					$changes['mode']        = Modes::PULL_IDLE;
					$changes['empty_runs']  = 0;
					$changes['next_due_at'] = $now + Cadence::collectionInterval( Modes::PULL_IDLE, 0 );
				}
			}

			return self::update( $changes, $state );
		}

		if ( $ateJobId < 1 || ! \WPML\TM\ATE\Receive\SingleJobDelivery::isRetryable( $outcome ) ) {
			return self::update( $changes, $state );
		}

		$alreadyArmedForThisJob = (int) $state['last_ate_ready_job_id'] === $ateJobId
			&& Modes::isPull( $state['mode'] )
			&& ! self::isCollectionDue( $state );

		if ( $alreadyArmedForThisJob ) {
			return self::update( $changes, $state );
		}

		$changes['mode']                  = Modes::SUSPECT === $state['mode'] ? Modes::SUSPECT : Modes::PULL_ACTIVE;
		$changes['empty_runs']            = 0;
		$changes['next_due_at']           = $now;
		$changes['pacer_id']              = '';
		$changes['pacer_lease_until']     = 0;
		$changes['last_ate_ready_job_id'] = $ateJobId;

		return self::update( $changes, $state );
	}

	private static function pendingReading( ?array $pending, array $state ) {
		if ( null === $pending ) {
			return [
				'pending_count'               => max( 0, (int) $state['pending_count'] ),
				'oldest_pending_at'           => max( 0, (int) $state['oldest_pending_at'] ),
				'oldest_automatic_pending_at' => max( 0, (int) $state['oldest_automatic_pending_at'] ),
			];
		}

		$count = max( 0, isset( $pending['count'] ) ? (int) $pending['count'] : 0 );

		return [
			'pending_count'               => $count,
			'oldest_pending_at'           => $count > 0 ? max( 0, isset( $pending['oldest_at'] ) ? (int) $pending['oldest_at'] : 0 ) : 0,
			'oldest_automatic_pending_at' => $count > 0 ? max( 0, isset( $pending['oldest_automatic_at'] ) ? (int) $pending['oldest_automatic_at'] : 0 ) : 0,
		];
	}

	public static function onWatchdogStale( $oldestPendingAt, $raisedBy = self::RAISED_BY_WATCHDOG ) {
		$state = self::getFresh();
		$now   = time();

		$changes = [ 'oldest_pending_at' => max( 0, (int) $oldestPendingAt ) ];

		if ( Modes::PUSH_OK === $state['mode'] ) {
			$changes['mode']              = Modes::SUSPECT;
			$changes['suspect_since']     = $now;
			$changes['suspect_raised_by'] = $raisedBy;
			$changes['empty_runs']        = 0;
			$changes['next_due_at']       = $now;
		}

		return self::update( $changes, $state );
	}

	public static function onPickUpRequested( $oldestPendingAt ) {
		JobLog::maybeInitRequest();
		JobLog::createNewGroup( JobLog::GROUP_ID_DOWNLOAD_JOBS, 'ATE pull delivery pick-up' );
		JobLog::add(
			'pull_pickup_requested',
			[
				'oldest_pending_at' => max( 0, (int) $oldestPendingAt ),
				'pending'           => self::getKey( 'pending_count' ),
			]
		);
		JobLog::finishCurrentGroup();

		return self::onWatchdogStale( $oldestPendingAt, self::RAISED_BY_PICK_UP );
	}

	public static function onAteUnreachable() {
		$state = self::getFresh();

		if ( Modes::isPull( $state['mode'] ) || $state['pending_count'] < 1 ) {
			return $state;
		}

		return self::update(
			[
				'mode'              => Modes::PULL_ACTIVE,
				'suspect_since'     => 0,
				'suspect_raised_by' => '',
				'empty_runs'        => 0,
				'next_due_at'       => self::dueTimeOnAteUnreachable( $state ),
			],
			$state
		);
	}

	private static function dueTimeOnAteUnreachable( array $state ) {
		$kept = (int) $state['next_due_at'];

		if ( $kept > 0 ) {
			return $kept;
		}

		$now           = time();
		$recentWebhook = (int) $state['last_webhook_at'] > $now - Cadence::stalenessThreshold();

		return $recentWebhook
			? $now + Cadence::collectionInterval( Modes::PULL_ACTIVE, 0 )
			: $now;
	}

	public static function deferCollection( $until ) {
		return self::update( [ 'next_due_at' => max( 1, (int) $until ) ] );
	}

	public static function settleSuspect( $reachable, $applied = 0 ) {
		$state = self::getFresh();

		if ( Modes::SUSPECT !== $state['mode'] ) {
			return $state;
		}

		$now            = time();
		$pushMissedWork = self::RAISED_BY_WATCHDOG === $state['suspect_raised_by'] && (int) $applied > 0;

		if ( $reachable && ! $pushMissedWork ) {
			return self::update(
				[
					'mode'                  => Modes::PUSH_OK,
					'suspect_since'         => 0,
					'suspect_raised_by'     => '',
					'suspect_settled_at'    => self::RAISED_BY_WATCHDOG === $state['suspect_raised_by'] ? $now : (int) $state['suspect_settled_at'],
					'empty_runs'            => 0,
					'next_due_at'           => 0,
					'draining'              => false,
					'reachability_asked_at' => $now,
				],
				$state
			);
		}

		$mode     = $state['pending_count'] > 0 ? Modes::PULL_ACTIVE : Modes::PULL_IDLE;
		$draining = Modes::PULL_ACTIVE === $mode && ! empty( $state['draining'] );

		return self::update(
			[
				'mode'                  => $mode,
				'suspect_since'         => 0,
				'suspect_raised_by'     => '',
				'empty_runs'            => 0,
				'next_due_at'           => $draining ? $now : $now + Cadence::collectionInterval( $mode, 0 ),
				'draining'              => $draining,
				'reachability_asked_at' => $now,
			],
			$state
		);
	}

	public static function mayDoubtAgain( array $state, $threshold ) {
		return (int) $state['suspect_settled_at'] <= time() - (int) $threshold;
	}

	public static function onReachabilityAsked( $reachable ) {
		$state = self::getFresh();

		$changes = [ 'reachability_asked_at' => time() ];

		if ( $reachable && Modes::isPull( $state['mode'] ) ) {
			$changes['mode']               = Modes::PUSH_OK;
			$changes['suspect_since']      = 0;
			$changes['empty_runs']         = 0;
			$changes['next_due_at']        = 0;
			$changes['manual_force_until'] = 0;
			$changes['pacer_id']           = '';
			$changes['pacer_lease_until']  = 0;
		}

		return self::update( $changes, $state );
	}

	public static function isReachabilityEvidenceFresh( ?array $state = null ) {
		$state   = null === $state ? self::get() : $state;
		$askedAt = isset( $state['reachability_asked_at'] ) ? (int) $state['reachability_asked_at'] : 0;
		$latest  = max( $askedAt, (int) $state['last_webhook_at'] );

		return $latest > time() - Cadence::reachabilityRecheckInterval();
	}

	public static function forceManualPull() {
		$state = self::getFresh();
		$now   = time();

		$changes = [
			'mode'               => Modes::PULL_ACTIVE,
			'manual_force_until' => $now + Cadence::manualForceWindow(),
			'suspect_since'      => 0,
		];

		if ( $state['manual_force_until'] <= $now ) {
			$changes['empty_runs']  = 0;
			$changes['next_due_at'] = $now;
		}

		return self::update( $changes, $state );
	}

	public static function onCollectionArranged() {
		return self::arrangeCollection( self::getFresh() );
	}

	public static function arrangeCollectionIfDue() {
		$state = self::getFresh();

		return self::isCollectionDue( $state ) ? self::arrangeCollection( $state ) : null;
	}

	public static function isInWebhookQuiet( array $state ) {
		$lastWebhookAt = (int) $state['last_webhook_at'];

		return $lastWebhookAt > 0 && time() - $lastWebhookAt < Cadence::RECENT_WEBHOOK_QUIET;
	}

	private static function arrangeCollection( array $state ) {
		$now = time();

		$previous = (int) $state['arranged_at'];
		$missed   = $previous > 0
			&& (int) $state['last_run_at'] < $previous
			&& (int) $state['collecting_since'] < $previous;

		return self::update(
			[
				'next_due_at'  => $now + Cadence::collectingBound(),
				'arranged_at'  => $now,
				'spawn_misses' => $missed ? max( 0, (int) $state['spawn_misses'] ) + 1 : 0,
			],
			$state
		);
	}

	public static function onCollectionStart() {
		$state = self::getFresh();

		return self::update(
			[
				'collecting_since'            => time(),
				'collection_ate_ready_job_id' => (int) $state['last_ate_ready_job_id'],
			],
			$state
		);
	}

	public static function onCollectionResult( $appliedCount, $pendingCount, $stillInProgress = 0, $oldestPendingAt = 0, $oldestAutomaticPendingAt = 0, $ateTimedOut = false, $ateRefusedLocally = false, $moreReady = false, $slowestApplyMs = 0 ) {
		$state        = self::getFresh();
		$now          = time();
		$applied      = max( 0, (int) $appliedCount );
		$pendingCount = max( 0, (int) $pendingCount );
		$jobBecameReadyDuringRun =
			$pendingCount > 0
			&& (int) $state['last_ate_ready_job_id'] > 0
			&& (int) $state['last_ate_ready_job_id'] !== (int) $state['collection_ate_ready_job_id'];

		$waitingOnAte = Modes::isPull( $state['mode'] ) && $pendingCount > 0;

		if ( $applied > 0 || $jobBecameReadyDuringRun ) {
			$emptyRuns = 0;
		} elseif ( $ateTimedOut ) {
			$emptyRuns = max( 0, (int) $state['empty_runs'] ) + 1;
		} elseif ( $ateRefusedLocally ) {
			$emptyRuns = max( 0, (int) $state['empty_runs'] );
		} elseif ( $waitingOnAte ) {
			$emptyRuns = max( 0, (int) $state['empty_runs'] );
		} else {
			$emptyRuns = max( 0, (int) $state['empty_runs'] ) + 1;
		}

		$changes = [
			'last_run_at'       => $now,
			'pending_count'     => $pendingCount,
			'still_in_progress' => max( 0, (int) $stillInProgress ),
			'oldest_pending_at' => $pendingCount > 0 ? max( 0, (int) $oldestPendingAt ) : 0,
			'oldest_automatic_pending_at' => $pendingCount > 0 ? max( 0, (int) $oldestAutomaticPendingAt ) : 0,
			'empty_runs'                 => $emptyRuns,
			'collecting_since'           => 0,
			'collection_ate_ready_job_id' => 0,
			'spawn_misses'               => 0,
			'draining'                   => false,
			'slowest_apply_ms'           => max( 0, (int) $slowestApplyMs ),
		];

		if ( $applied > 0 ) {
			$changes['last_received_at'] = $now;
		}

		$mode = $state['mode'];

		if ( Modes::SUSPECT === $mode ) {
			$changes['next_due_at'] = $now + Cadence::collectionInterval( $mode, $changes['empty_runs'] );
			$changes['draining']    = (bool) $moreReady;

			return self::update( $changes, $state );
		}

		if ( Modes::isPull( $mode ) ) {
			$forced = $state['manual_force_until'] > $now;
			$mode   = ( $pendingCount > 0 || $forced ) ? Modes::PULL_ACTIVE : Modes::PULL_IDLE;

			$changes['mode'] = $mode;

			if ( $jobBecameReadyDuringRun ) {
				$changes['mode']              = Modes::PULL_ACTIVE;
				$changes['next_due_at']       = $now;
				$changes['pacer_id']          = '';
				$changes['pacer_lease_until'] = 0;
			} elseif ( $moreReady ) {
				$changes['mode']        = Modes::PULL_ACTIVE;
				$changes['next_due_at'] = $now;
				$changes['empty_runs']  = 0;
				$changes['draining']    = true;
			} else {
				$changes['next_due_at'] = $now + Cadence::collectionInterval( $mode, $changes['empty_runs'] );
			}

			return self::update( $changes, $state );
		}

		$changes['next_due_at'] = 0;

		return self::update( $changes, $state );
	}

	public static function onStalledWorkUnderHealthyPush() {
		$state = self::getFresh();

		if ( Modes::PUSH_OK !== $state['mode'] ) {
			return $state;
		}

		if ( $state['ate_signals_due_since'] || self::isCollecting( $state ) ) {
			return $state;
		}

		return self::update( [ 'ate_signals_due_since' => time() ], $state );
	}

	public static function onSignalsReadArranged() {
		return self::update(
			[
				'ate_signals_due_since' => 0,
				'pacer_id'              => '',
				'pacer_lease_until'     => 0,
			]
		);
	}

	public static function isSignalsReadDue( ?array $state = null ) {
		$state = null === $state ? self::get() : $state;

		if ( ! $state['ate_signals_due_since'] ) {
			return false;
		}

		return ! self::isCollecting( $state );
	}

	public static function recordWatchdogCheck( $pendingCount, $oldestPendingAt, $oldestAutomaticPendingAt = 0 ) {
		$pendingCount = max( 0, (int) $pendingCount );

		$changes = [
			'pending_count'       => $pendingCount,
			'oldest_pending_at'   => $pendingCount > 0 ? max( 0, (int) $oldestPendingAt ) : 0,
			'oldest_automatic_pending_at' => $pendingCount > 0 ? max( 0, (int) $oldestAutomaticPendingAt ) : 0,
			'watchdog_checked_at' => time(),
		];

		if ( $pendingCount < 1 ) {
			$changes['ate_signals_due_since'] = 0;
		}

		return self::update( $changes );
	}

	public static function recordLoopbackProbe( $ok ) {
		return self::update( [ 'loopback_ok' => (bool) $ok ] );
	}


	public static function isCollecting( ?array $state = null ) {
		$state = null === $state ? self::get() : $state;

		$since = (int) $state['collecting_since'];

		if ( $since < 1 ) {
			return false;
		}

		return $since > time() - Cadence::collectingBound();
	}

	public static function isCollectionDue( ?array $state = null ) {
		$state = null === $state ? self::get() : $state;

		if ( ! $state['next_due_at'] ) {
			return false;
		}

		if ( self::isCollecting( $state ) ) {
			return false;
		}

		return $state['next_due_at'] <= time();
	}

	public static function secondsUntilDue( ?array $state = null ) {
		$state = null === $state ? self::get() : $state;
		$due   = (int) $state['next_due_at'];

		if ( $due < 1 ) {
			return null;
		}

		return max( 0, $due - time() );
	}

	public static function snapshot( ?array $state = null ) {
		$state = null === $state ? self::get() : $state;
		$now   = time();

		$nextCheckIn = $state['next_due_at'] ? max( 0, $state['next_due_at'] - $now ) : null;

		$snapshot = [
			'mode'             => $state['mode'],
			'pendingCount'     => (int) $state['pending_count'],
			'stillInProgress'  => (int) $state['still_in_progress'],
			'lastReceivedAt'   => (int) $state['last_received_at'],
			'lastRunAt'        => (int) $state['last_run_at'],
			'nextCheckIn'      => $nextCheckIn,
			'oldestPendingAt'  => (int) $state['oldest_pending_at'],
			'isStale'          => self::isStaleForAutomaticWork( $state ),
			'offersPickUp'     => (int) $state['pending_count'] > 0,
			'showsDeliveryLine' => Modes::showsDeliveryLine( $state['mode'] ),
			'serverTime'       => $now,
			'clientRestricted' => (bool) $state['client_restricted'],
			'clientRestrictionDebtorBlock' => (string) $state['client_restriction_debtor'],
			'spendCap'         => Collector::spendCapForState() ?: null,
			'lastAppliedJobs'  => array_values( (array) $state['last_applied_jobs'] ),
			'jobErrorRevision' => (int) $state['job_error_revision'],
		];

		if ( $state['ate_signals_at'] ) {
			$snapshot['ateSignalsAt']              = (int) $state['ate_signals_at'];
			$snapshot['insufficientBalanceJobIds'] = array_values( (array) $state['insufficient_balance_job_ids'] );
			$snapshot['paymentPendingJobIds']      = array_values( (array) $state['payment_pending_job_ids'] );
			$snapshot['unsolvableJobIds']          = array_values( (array) $state['unsolvable_job_ids'] );
			$snapshot['etaAvailable']              = (bool) $state['eta_available'];
			$snapshot['etaMinutes']                = (int) $state['eta_minutes'];
		}

		return $snapshot;
	}

	public static function isStale( ?array $state = null, $firstContact = false ) {
		$state = null === $state ? self::get() : $state;

		$threshold = $firstContact ? Cadence::firstContactThreshold() : Cadence::stalenessThreshold();

		if ( ! self::hasWorkOlderThanThreshold( $state, 'oldest_pending_at', $threshold ) ) {
			return false;
		}

		return $state['last_webhook_at'] <= time() - $threshold;
	}

	public static function isStaleForAutomaticWork( ?array $state = null ) {
		$state = null === $state ? self::get() : $state;

		if ( ! self::hasWorkOlderThanThreshold( $state, 'oldest_automatic_pending_at' ) ) {
			return false;
		}

		return $state['last_webhook_at'] <= time() - Cadence::stalenessThreshold();
	}

	public static function hasStalledWorkUnderHealthyPush( ?array $state = null ) {
		$state = null === $state ? self::get() : $state;

		$automaticPendingSince = isset( $state['oldest_automatic_pending_at'] ) ? (int) $state['oldest_automatic_pending_at'] : 0;

		if ( $automaticPendingSince < 1 ) {
			return false;
		}

		return time() - (int) $state['last_webhook_at'] > Cadence::stalenessThreshold();
	}

	private static function hasWorkOlderThanThreshold( array $state, $referenceKey, $threshold = null ) {
		$reference = isset( $state[ $referenceKey ] ) ? (int) $state[ $referenceKey ] : 0;

		if ( $reference < 1 ) {
			return false;
		}

		$threshold = null === $threshold ? Cadence::stalenessThreshold() : (int) $threshold;

		return $reference <= time() - $threshold;
	}
}
