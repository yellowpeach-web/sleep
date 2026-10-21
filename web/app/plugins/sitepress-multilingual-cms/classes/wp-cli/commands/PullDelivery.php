<?php
namespace WPML\CLI\Core\Commands;

use WPML\TM\ATE\PullDelivery\Cadence;
use WPML\TM\ATE\PullDelivery\Modes;
use WPML\TM\ATE\PullDelivery\State;

class PullDelivery implements ICommand {

	public function __invoke( $args, $assoc_args ) {
		$subcommand = isset( $args[0] ) ? (string) $args[0] : 'state';

		switch ( $subcommand ) {
			case 'state':
				$this->dumpState();
				break;
			case 'set-mode':
				$this->setMode( isset( $args[1] ) ? (string) $args[1] : '' );
				break;
			default:
				\WP_CLI::error( 'Unknown subcommand "' . $subcommand . '". Use "state" or "set-mode".' );
		}
	}

	private function dumpState() {
		$state = State::get();

		\WP_CLI::log(
			(string) wp_json_encode(
				[
					'state'    => $state,
					'snapshot' => State::snapshot( $state ),
					'due'      => State::isCollectionDue( $state ),
					'cadence'  => [
						'activeFloor'        => Cadence::activeFloor(),
						'activeCeiling'      => Cadence::activeCeiling(),
						'idleFloor'          => Cadence::idleFloor(),
						'idleCeiling'        => Cadence::idleCeiling(),
						'leaseWatch'         => Cadence::leaseWatch(),
						'stalenessThreshold' => Cadence::stalenessThreshold(),
						'minPing'            => Cadence::minPing(),
						'maxJobsPerRun'      => Cadence::maxJobsPerRun(),
						'maxSecondsPerRun'   => Cadence::maxSecondsPerRun(),
					],
				],
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
			)
		);
	}

	private function setMode( $mode ) {
		if ( ! $this->modeWritingAllowed() ) {
			\WP_CLI::error(
				'set-mode is a lab control. Enable WP_DEBUG, or add a filter on '
				. '"wpml_pull_delivery_allow_cli_set_mode" returning true, to use it.'
			);
		}

		if ( ! Modes::isValid( $mode ) ) {
			\WP_CLI::error( 'Unknown mode "' . $mode . '". Use one of: ' . implode( ', ', Modes::all() ) . '.' );
		}

		$next = [ 'mode' => $mode ];

		$interval = Cadence::collectionInterval( $mode, 0 );
		$next['next_due_at']   = $interval > 0 ? time() : 0;
		$next['empty_runs']    = 0;
		$next['suspect_since'] = Modes::SUSPECT === $mode ? time() : 0;
		$next['suspect_raised_by'] = '';

		State::update( $next );

		\WP_CLI::success( 'Pull-delivery mode set to ' . $mode . '.' );
	}

	private function modeWritingAllowed() {
		$isDebug = defined( 'WP_DEBUG' ) && WP_DEBUG;

		return (bool) apply_filters( 'wpml_pull_delivery_allow_cli_set_mode', $isDebug );
	}

	public function get_command() {
		return 'pull-delivery';
	}
}
