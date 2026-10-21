<?php

namespace WPML\TM\ATE\PullDelivery;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;

class AjaxPing implements IHandler {

	private $handler;

	public function __construct( PingHandler $handler ) {
		$this->handler = $handler;
	}

	public function run( Collection $data ) {
		if ( ! Capability::userMayPing() ) {
			return Either::left( [ 'error' => 'forbidden' ] );
		}

		return Either::of(
			$this->handler->handle(
				(string) $data->get( 'tabId', '' ),
				ForceFlag::wasRequested( $data->get( 'force', false ) ),
				ForceFlag::wasRequested( $data->get( 'wake', false ) )
			)
		);
	}
}
