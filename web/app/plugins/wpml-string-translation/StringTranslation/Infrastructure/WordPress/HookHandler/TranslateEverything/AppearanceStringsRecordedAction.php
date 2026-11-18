<?php

namespace WPML\StringTranslation\Infrastructure\WordPress\HookHandler\TranslateEverything;

use WPML\StringTranslation\Infrastructure\StringHtml\Command\FrontendStringsObserverBridge;
use WPML\StringTranslation\Infrastructure\WordPress\HookHandler\AbstractActionHookHandler;

class AppearanceStringsRecordedAction extends AbstractActionHookHandler {

	const ACTION_NAME     = 'wpml_appearance_strings_recorded';
	const ACTION_PRIORITY = 10;
	const ACTION_ARGS     = 1;

	private $observers;

	public function __construct( FrontendStringsObserverBridge $observers ) {
		$this->observers = $observers;
	}

	protected function onAction( ...$args ) {
		$ids = is_array( $args[0] ?? null ) ? $args[0] : [];
		$ids = array_values(
			array_filter(
				array_map(
					function ( $id ) {
						return is_numeric( $id ) ? (int) $id : 0;
					},
					$ids
				),
				function ( $id ) {
					return $id > 0;
				}
			)
		);

		$this->observers->newFrontendStringsRecorded( $ids );
	}
}
