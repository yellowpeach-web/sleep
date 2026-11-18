<?php

namespace WPML\StringTranslation\Infrastructure\StringHtml\Command;

use WPML\StringTranslation\Application\StringHtml\Command\ProcessFrontendStringsObserverInterface;

class FrontendStringsObserverBridge {

	private $build;

	private $observers;

	public function __construct( callable $build ) {
		$this->build = $build;
	}

	public function observers(): array {
		if ( null === $this->observers ) {
			$this->observers = ( $this->build )();
		}

		return $this->observers;
	}

	public function newFrontendStringsRecorded( array $stringIds ) {
		if ( ! $stringIds ) {
			return;
		}

		foreach ( $this->observers() as $observer ) {
			$observer->newFrontendStringsRegistered( $stringIds );
		}
	}
}
