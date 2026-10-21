<?php

namespace WPML\Setup\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\FP\Right;
use WPML\Setup\DeferredUpgradePass;

class FinishDeferredUpgrade implements IHandler {

	public function run( Collection $data ) {
		return Right::of( [ 'done' => DeferredUpgradePass::run() ] );
	}
}
