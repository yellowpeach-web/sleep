<?php

namespace WPML\TM\ATE\Sync;

class Result {

	public $ateToken;

	public $nextPage;

	public $numberOfPages;

	public $downloadQueueSize = 0;

	public $jobs = [];

	public $askedAteJobIds = [];

	public $eta;

	public $ateTransportFailure = false;

	public $ateRefusedLocally = false;

	public $clientRestriction = null;

	public $spendCap = null;
}
