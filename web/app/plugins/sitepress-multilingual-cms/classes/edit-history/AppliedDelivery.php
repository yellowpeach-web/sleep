<?php

namespace WPML\EditHistory;

class AppliedDelivery {

	public $language;

	public $originalId;

	public $jobId;

	public $ateJobId;

	public $deliveryId;

	public $entryId;

	public $appliedAt;

	public $base;

	public $baseHash;

	public $itemReason;

	public $copy;

	public function __construct( string $language, int $originalId, int $jobId, int $ateJobId, $deliveryId, int $entryId, int $appliedAt, array $base, string $baseHash, $itemReason, $copy ) {
		$this->language   = $language;
		$this->originalId = $originalId;
		$this->jobId      = $jobId;
		$this->ateJobId   = $ateJobId;
		$this->deliveryId = $deliveryId;
		$this->entryId    = $entryId;
		$this->appliedAt  = $appliedAt;
		$this->base       = $base;
		$this->baseHash   = $baseHash;
		$this->itemReason = $itemReason;
		$this->copy       = $copy;
	}
}
