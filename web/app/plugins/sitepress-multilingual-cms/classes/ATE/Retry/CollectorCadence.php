<?php

namespace WPML\TM\ATE\Retry;

use WPML\Core\Security\ExecutionContext\ExecutionContext;
use WPML\Core\Security\ExecutionContext\ExecutionContextHolder;
use function WPML\Container\make;

class CollectorCadence {

	const ACTOR = 'system:ate-retry-cadence';

	private $trigger;

	public function __construct( ?Trigger $trigger = null ) {
		$this->trigger = $trigger ?: new Trigger();
	}

	public function run() {
		if ( ! $this->trigger->isRetryRequired() ) {
			return false;
		}

		$process = make( Process::class );

		ExecutionContextHolder::within(
			ExecutionContext::request( 0, self::ACTOR ),
			function () use ( $process ) {
				$process->run( null );
			}
		);

		return true;
	}
}
