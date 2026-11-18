<?php

namespace WPML\EditHistory;

class DeliveryDecision {

	const APPLY = 'apply';

	const SKIP = 'skip';

	public $outcome = self::APPLY;

	public $reason;

	public $jobId = 0;

	public $ateJobId = 0;

	public $answer;

	public $subject;

	public $language = '';

	public $originalId = 0;

	public $state;

	public $itemReason;

	public $copy;

	public $removedUnits = [];

	public $dropped;

	public static function apply( int $jobId, int $ateJobId, DeliveryAnswer $answer ): self {
		$decision           = new self();
		$decision->jobId    = $jobId;
		$decision->ateJobId = $ateJobId;
		$decision->answer   = $answer;

		return $decision;
	}

	public function skip( string $reason ): self {
		$this->outcome = self::SKIP;
		$this->reason  = $reason;

		return $this;
	}

	public function writes(): bool {
		return self::APPLY === $this->outcome;
	}
}
