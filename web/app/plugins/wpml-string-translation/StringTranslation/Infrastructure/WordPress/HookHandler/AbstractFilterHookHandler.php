<?php
namespace WPML\StringTranslation\Infrastructure\WordPress\HookHandler;

abstract class AbstractFilterHookHandler implements HookHandlerInterface {

	const FILTER_NAME = '';
	const FILTER_ARGS = 1;
	const FILTER_PRIORITY = 10;

	abstract protected function onFilter( ...$args );

	public function load() {
		add_filter( static::FILTER_NAME, function( ...$args) {
			return $this->onFilter( ...$args );
		}, static::FILTER_PRIORITY, static::FILTER_ARGS );
	}

	public static function loadLazily( callable $make ) {
		$handler = null;
		add_filter( static::FILTER_NAME, function( ...$args ) use ( $make, &$handler ) {
			if ( null === $handler ) {
				$handler = $make();
			}

			return $handler->onFilter( ...$args );
		}, static::FILTER_PRIORITY, static::FILTER_ARGS );
	}
}