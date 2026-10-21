<?php

namespace WPML\Media;

use WPML\Core\BackgroundTask\Service\BackgroundTaskService;
use WPML\TM\Settings\ProcessExistingMediaInPosts;
use function WPML\Container\make;

class ActivateHandleMediaAuto {
	private $backgroundTaskService;

	public function __construct( BackgroundTaskService $backgroundTaskService ) {
		$this->backgroundTaskService = $backgroundTaskService;
	}

	public function activate() {
		Option::setShouldHandleMediaAuto( true );
		Option::removeShouldEnableHandleMediaAutoOnStActivation();
		Option::removeShouldShowHandleMediaAutoNotice30DaysAfterUpgrade();

		$this->backgroundTaskService->add(
			make( ProcessExistingMediaInPosts::class ),
			wpml_collect( [] )
		);
	}
}
