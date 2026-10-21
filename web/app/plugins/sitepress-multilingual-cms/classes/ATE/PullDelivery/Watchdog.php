<?php

namespace WPML\TM\ATE\PullDelivery;

use WPML\TM\Jobs\JobLog;

class Watchdog {

	private $pendingJobs;

	private $reachability;

	public function __construct( PendingJobs $pendingJobs, Reachability $reachability ) {
		$this->pendingJobs  = $pendingJobs;
		$this->reachability = $reachability;
	}

	public function makeFirstContact( ?array $state = null ) {
		$state = null === $state ? State::get() : $state;

		if ( (int) $state['pending_count'] < 1 ) {
			return $state;
		}

		if ( State::isReachabilityEvidenceFresh( $state ) ) {
			return $state;
		}

		$hasRecord = $this->reachability->hasRecord();

		$reachable = $this->reachability->recheck();

		JobLog::maybeInitRequest();
		JobLog::createNewGroup( JobLog::GROUP_ID_DOWNLOAD_JOBS, 'ATE pull delivery watchdog' );
		JobLog::add(
			'pull_watchdog_first_contact',
			[
				'pending'   => $state['pending_count'],
				'reachable' => null === $reachable ? 'no_verdict' : $reachable,
				'reason'    => $hasRecord ? 'stale_answer' : 'no_answer',
			]
		);
		JobLog::finishCurrentGroup();

		if ( null === $reachable ) {
			return $state;
		}

		return State::onReachabilityAsked( $reachable );
	}

	public function evaluate( ?array $state = null ) {
		$state = null === $state ? State::get() : $state;

		if ( Modes::PUSH_OK !== $state['mode'] ) {
			return $state;
		}

		if ( $this->hasRecentWebhook( $state ) ) {
			return $this->keepPushForRecentWebhook( $state );
		}

		if ( $state['pending_count'] > 0 && $this->reachability->isRecordedUnreachable() ) {
			$next = State::onAteUnreachable();

			JobLog::maybeInitRequest();
			JobLog::createNewGroup( JobLog::GROUP_ID_DOWNLOAD_JOBS, 'ATE pull delivery watchdog' );
			JobLog::add(
				'pull_watchdog_ate_unreachable',
				[
					'pending'     => $state['pending_count'],
					'next_due_at' => $next['next_due_at'],
				]
			);
			JobLog::finishCurrentGroup();

			return $next;
		}

		$firstContact = $this->isFirstContact( $state );
		$floor        = $firstContact ? Cadence::firstContactThreshold() : Cadence::watchdogFloor();
		$threshold    = $firstContact ? Cadence::firstContactThreshold() : Cadence::stalenessThreshold();

		if ( time() - $state['watchdog_checked_at'] < $floor && ! $this->mayRaiseSuspicion( $state, $firstContact, $threshold ) ) {
			return $state;
		}

		$summary = $this->pendingJobs->summary();
		$state   = State::recordWatchdogCheck(
			$summary['count'],
			$summary['oldest_at'],
			isset( $summary['oldest_automatic_at'] ) ? $summary['oldest_automatic_at'] : 0
		);

		if ( ! State::isStale( $state, $firstContact ) ) {
			return State::hasStalledWorkUnderHealthyPush( $state )
				? $this->askAteAboutStalledWork( $state )
				: $state;
		}

		if ( ! State::mayDoubtAgain( $state, $threshold ) ) {
			return $state;
		}

		JobLog::maybeInitRequest();
		JobLog::createNewGroup( JobLog::GROUP_ID_DOWNLOAD_JOBS, 'ATE pull delivery watchdog' );
		JobLog::addError(
			'pull_watchdog_stale',
			[
				'pending'           => $summary['count'],
				'oldest_pending_at' => $summary['oldest_at'],
				'automatic_pending' => isset( $summary['automatic_count'] ) ? $summary['automatic_count'] : 0,
				'oldest_automatic_pending_at' => isset( $summary['oldest_automatic_at'] ) ? $summary['oldest_automatic_at'] : 0,
				'last_webhook_at'   => $state['last_webhook_at'],
				'threshold'         => $threshold,
				'first_contact'     => $firstContact,
			]
		);
		JobLog::finishCurrentGroup();

		return State::onWatchdogStale( $summary['oldest_at'] );
	}

	private function mayRaiseSuspicion( array $state, $firstContact, $threshold ) {
		return State::isStale( $state, $firstContact ) && State::mayDoubtAgain( $state, $threshold );
	}

	private function hasRecentWebhook( array $state ) {
		$lastWebhookAt = (int) $state['last_webhook_at'];

		return $lastWebhookAt > 0 && time() - $lastWebhookAt < Cadence::EXPECTED_WEBHOOK_LATENCY;
	}

	private function keepPushForRecentWebhook( array $state ) {
		$oldestPendingAt = (int) $state['oldest_pending_at'];
		$oldWork         = $state['pending_count'] > 0
			&& $oldestPendingAt > 0
			&& $oldestPendingAt <= time() - Cadence::stalenessThreshold();

		if ( $oldWork || ( $state['pending_count'] > 0 && $this->reachability->isRecordedUnreachable() ) ) {
			JobLog::maybeInitRequest();
			JobLog::createNewGroup( JobLog::GROUP_ID_DOWNLOAD_JOBS, 'ATE pull delivery watchdog' );
			JobLog::add(
				'pull_watchdog_recent_webhook',
				[
					'pending'           => $state['pending_count'],
					'oldest_pending_at' => $oldestPendingAt,
					'since_webhook'     => time() - (int) $state['last_webhook_at'],
				]
			);
			JobLog::finishCurrentGroup();
		}

		return $state;
	}

	private function isFirstContact( array $state ) {
		return (int) $state['last_webhook_at'] < 1 && ! $this->reachability->isRecordedReachable();
	}

	private function askAteAboutStalledWork( array $state ) {
		$next = State::onStalledWorkUnderHealthyPush();

		if ( $next['ate_signals_due_since'] === $state['ate_signals_due_since'] ) {
			return $next;
		}

		JobLog::maybeInitRequest();
		JobLog::createNewGroup( JobLog::GROUP_ID_DOWNLOAD_JOBS, 'ATE pull delivery watchdog' );
		JobLog::add(
			'pull_watchdog_stalled_under_healthy_push',
			[
				'pending'            => $state['pending_count'],
				'oldest_pending_at'  => $state['oldest_pending_at'],
				'last_webhook_at'    => $state['last_webhook_at'],
				'since_last_webhook' => time() - (int) $state['last_webhook_at'],
				'threshold'          => Cadence::stalenessThreshold(),
			]
		);
		JobLog::finishCurrentGroup();

		return $next;
	}
}
