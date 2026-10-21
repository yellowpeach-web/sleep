<?php

namespace WPML\Setup;

class DeferredUpgradePass {

	const RUNNER = '\WPML\ST\Upgrade\Deferred\Runner';

	const MAX_STEPS_PER_REQUEST = 40;

	const MAX_SECONDS_PER_REQUEST = 10;

	public static function run( $maxSteps = self::MAX_STEPS_PER_REQUEST, $maxSeconds = self::MAX_SECONDS_PER_REQUEST ) {
		if ( ! class_exists( self::RUNNER ) ) {
			return true;
		}

		if ( ! method_exists( self::RUNNER, 'runOneStepNow' ) ) {
			return true;
		}

		\WPML\ST\Upgrade\Deferred\Runner::queueDeferredSteps();

		$deadline = microtime( true ) + $maxSeconds;

		for ( $step = 0; $step < $maxSteps; $step++ ) {
			if ( ! \WPML\ST\Upgrade\Deferred\Runner::pendingSteps() ) {
				return true;
			}

			if ( ! \WPML\ST\Upgrade\Deferred\Runner::runOneStepNow( true ) ) {
				break;
			}

			if ( microtime( true ) >= $deadline ) {
				break;
			}
		}

		return ! \WPML\ST\Upgrade\Deferred\Runner::pendingSteps();
	}
}
