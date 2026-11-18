<?php

namespace WPML\PB\Gutenberg\Hooks;

use WPML\PB\Gutenberg\StringsInBlock\HTML;

class RawHtmlSetting extends \WPML\Compatibility\BaseRawHtmlSetting implements \WPML\PB\Gutenberg\Integration {

	public function add_hooks() {
		add_filter( 'wpml_found_strings_in_block', [ $this, 'removeHtmlBlockStringsWhenDisabled' ], 10, 2 );
	}

	public function removeHtmlBlockStringsWhenDisabled( $strings, $block ) {
		if ( HTML::HTML_BLOCK_NAME !== $block->blockName || $this->isRawHtmlTranslatable() ) {
			return $strings;
		}

		return [];
	}
}
