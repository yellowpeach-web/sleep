<?php

namespace WPML\TM\ATE\PullDelivery;

use WPML\TM\ATE\REST\PullCollect;
use WPML\TM\Jobs\JobLog;

class PingHandler {

	private $pacer;

	private $spawner;

	private $watchdog;

	private $liveSignals;

	private $noCreditResume;

	private $parkedJobs;

	private $unsolvableJobIds;

	public function __construct(
		Pacer $pacer,
		Spawner $spawner,
		Watchdog $watchdog,
		LiveSignals $liveSignals,
		NoCreditResume $noCreditResume,
		ParkedJobs $parkedJobs,
		?UnsolvableJobIds $unsolvableJobIds = null
	) {
		$this->pacer            = $pacer;
		$this->spawner          = $spawner;
		$this->watchdog         = $watchdog;
		$this->liveSignals      = $liveSignals;
		$this->noCreditResume   = $noCreditResume;
		$this->parkedJobs       = $parkedJobs;
		$this->unsolvableJobIds = $unsolvableJobIds ?: new UnsolvableJobIds();
	}

	public function handle( $tabId, $force = false, $wake = false ) {
		$state = $this->watchdog->evaluate();

		$state = $this->liveSignals->refresh( $state );

		$state = $this->parkedJobs->prune( $state );

		$state = $this->unsolvableJobIds->prune( $state );

		$state = $this->noCreditResume->maybeResume( $state, $wake );

		if ( $force ) {
			$state = $this->pickUp( $state );
		}

		$pacing = $this->pacer->pace( $tabId, $state );
		$state  = $pacing['state'];

		$sinceWebhook = time() - (int) $state['last_webhook_at'];

		if (
			! $force
			&& $pacing['isPacer']
			&& State::isCollectionDue( $state )
			&& State::isInWebhookQuiet( $state )
		) {
			$deferBy = Cadence::RECENT_WEBHOOK_QUIET - $sinceWebhook;
			$state   = State::deferCollection( time() + $deferBy );

			JobLog::maybeInitRequest();
			JobLog::createNewGroup( JobLog::GROUP_ID_DOWNLOAD_JOBS, 'ATE pull delivery ping' );
			JobLog::add(
				'pull_collect_deferred_recent_webhook',
				[
					'since_webhook' => $sinceWebhook,
					'deferred_by'   => $deferBy,
				]
			);
			JobLog::finishCurrentGroup();
		} elseif ( ( $force || $pacing['isPacer'] ) && State::isCollectionDue( $state ) ) {
			$state = State::onCollectionArranged();

			$this->spawner->spawn();
		} elseif ( ( $force || $pacing['isPacer'] ) && State::isSignalsReadDue( $state ) ) {
			$state = State::onSignalsReadArranged();

			$this->spawner->spawn();
		}

		$drains = $pacing['isPacer'] && ! empty( $state['draining'] ) && Modes::isPull( $state['mode'] );

		$answer = [
			'next_ping_in' => $this->nextPingIn( $state, $pacing['isPacer'], $drains ),
			'snapshot'     => State::snapshot( $state ),
		];

		if ( $drains ) {
			$answer['more_ready'] = true;
		}

		$handoff = Spawner::handoffToken();
		if ( $handoff ) {
			$answer['collect'] = [
				'url'   => PullCollect::url(),
				'token' => $handoff,
			];
		}

		return $answer;
	}

	private function nextPingIn( array $state, $isPacer, $drains ) {
		$cadence = Cadence::pingCadence(
			$state['mode'],
			$state['empty_runs'],
			$isPacer,
			$state['pending_count'],
			$drains
		);

		if ( ! $isPacer || $drains || Cadence::collectionInterval( $state['mode'], $state['empty_runs'] ) <= 0 ) {
			return $cadence;
		}

		$dueIn = State::secondsUntilDue( $state );

		if ( null === $dueIn ) {
			return $cadence;
		}

		return max( Cadence::minPing(), min( $cadence, $dueIn ) );
	}

	private function pickUp( array $state ) {
		if ( Modes::isPull( $state['mode'] ) ) {
			return State::forceManualPull();
		}

		if ( (int) $state['pending_count'] < 1 ) {
			return $state;
		}

		return State::onPickUpRequested( $state['oldest_pending_at'] );
	}
}
