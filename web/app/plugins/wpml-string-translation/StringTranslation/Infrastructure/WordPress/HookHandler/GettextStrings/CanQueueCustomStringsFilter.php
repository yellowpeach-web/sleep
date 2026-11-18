<?php

namespace WPML\StringTranslation\Infrastructure\WordPress\HookHandler\GettextStrings;

use WPML\StringTranslation\Application\StringGettext\Service\GettextStringsService;
use WPML\StringTranslation\Infrastructure\WordPress\HookHandler\AbstractFilterHookHandler;

class CanQueueCustomStringsFilter extends AbstractFilterHookHandler {

	const FILTER_NAME = 'wpml_st_can_queue_custom_strings';
	const FILTER_ARGS = 1;

	private $gettextStringsService;

	public function __construct( GettextStringsService $gettextStringsService ) {
		$this->gettextStringsService = $gettextStringsService;
	}

	protected function onFilter( ...$args ) {
		return $this->gettextStringsService->canQueueCustomStringsForCurrentRequest();
	}
}
