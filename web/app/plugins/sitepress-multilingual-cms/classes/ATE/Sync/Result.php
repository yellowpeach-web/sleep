<?php

namespace WPML\TM\ATE\Sync;

class Result {

	public $lockKey;

	public $ateToken;

	public $nextPage;

	public $numberOfPages;

	public $downloadQueueSize = 0;

	public $jobs = [];

	public $eta;

	public $ateTransportFailure = false;

	public $ateRefusedLocally = false;

	public $clientRestriction = null;

	public $spendCap = null;
}
