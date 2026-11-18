<?php

namespace WPML\Compatibility;

abstract class BaseRawHtmlShortcodeSetting extends BaseRawHtmlSetting {

	const PRIORITY_SHOULD_HANDLE_CONTENT = 10;

	public function add_hooks() {
		add_filter( 'wpml_pb_should_handle_content', [ $this, 'skipCodeContentWhenDisabled' ], static::PRIORITY_SHOULD_HANDLE_CONTENT, 2 );
	}

	public function skipCodeContentWhenDisabled( $shouldHandleContent, $shortcode ) {
		if ( in_array( $shortcode['tag'] ?? null, $this->getCodeShortcodes(), true ) && ! $this->isRawHtmlTranslatable() ) {
			return false;
		}

		return $shouldHandleContent;
	}

	abstract protected function getCodeShortcodes(): array;
}
