<?php

namespace WPML\Compatibility;

use WPML\LIB\WP\Hooks;

use function WPML\FP\spreadArgs;

abstract class BaseRawHtmlNodesSetting extends BaseRawHtmlSetting {

	const PRIORITY_AFTER_CONFIG = \WPML\PB\Config\Hooks::PRIORITY_AFTER_DEFAULT + 1;

	public function add_hooks() {
		Hooks::onFilter( $this->getNodesToTranslateFilter(), self::PRIORITY_AFTER_CONFIG )
			->then( spreadArgs( [ $this, 'removeHtmlNodeWhenDisabled' ] ) );
	}

	public function removeHtmlNodeWhenDisabled( $nodes ) {
		if ( is_array( $nodes ) && ! $this->isRawHtmlTranslatable() ) {
			$nodes = $this->withoutRawHtmlNodes( $nodes );
		}

		return $nodes;
	}

	protected function withoutRawHtmlNodes( array $nodes ): array {
		unset( $nodes[ $this->getRawHtmlNode() ] );

		return $nodes;
	}

	abstract protected function getNodesToTranslateFilter(): string;

	abstract protected function getRawHtmlNode(): string;
}
