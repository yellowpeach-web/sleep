<?php

namespace WPML\TM\ATE\Handshake\Upgrade;

use WPML\TM\ATE\Handshake\CronTick;
use WPML\TM\ATE\Handshake\State;
use WPML\TM\ATE\PullDelivery\PendingJobs;

class StampInFlightAfterUpdate implements \IWPML_Upgrade_Command {

	private $summary;

	private $result = false;

	public function __construct( $args = [] ) {
		$this->summary = isset( $args[0] ) && is_callable( $args[0] )
			? $args[0]
			: function () {
				return ( new PendingJobs() )->summary();
			};
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return $this->run();
	}

	public function get_results() {
		return $this->result;
	}

	private function run() {
		$summary = (array) call_user_func( $this->summary );

		if ( isset( $summary['count'] ) && (int) $summary['count'] > 0 ) {
			State::recordInFlightSummary( $summary );
			CronTick::reconcile();
		}

		return $this->result = true;
	}
}
