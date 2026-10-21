<?php

namespace WPML\TM\ATE\PullDelivery;

use WPML\TM\Jobs\JobLog;

class SuspectResolver {

	private $reachability;

	private $collector;

	public function __construct( Reachability $reachability, Collector $collector ) {
		$this->reachability = $reachability;
		$this->collector    = $collector;
	}

	public function resolve() {
		if ( Modes::SUSPECT !== State::mode() ) {
			return;
		}

		$raisedBy  = (string) State::getKey( 'suspect_raised_by', '' );
		$reachable = $this->reachability->recheck();

		$result = $this->collector->run();

		if ( Collector::EXIT_LOCK_BUSY === ( $result['reason'] ?? '' ) ) {
			return;
		}

		$applied = (int) ( $result['applied'] ?? 0 );
		$state   = State::settleSuspect( $reachable, $applied );

		JobLog::maybeInitRequest();
		JobLog::createNewGroup( JobLog::GROUP_ID_DOWNLOAD_JOBS, 'ATE pull delivery suspicion settled' );
		JobLog::add(
			'pull_suspect_settled',
			[
				'reachable' => $reachable,
				'raised_by' => $raisedBy,
				'applied'   => $applied,
				'mode'      => $state['mode'],
			]
		);
		JobLog::finishCurrentGroup();
	}
}
