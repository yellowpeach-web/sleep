<?php

namespace WPML\PB\Cornerstone\Hooks;

class RawHtmlSetting extends \WPML\Compatibility\BaseRawHtmlNodesSetting {

	const ELEMENT = 'raw-content';

	protected function getNodesToTranslateFilter(): string {
		return 'wpml_cornerstone_modules_to_translate';
	}

	protected function getRawHtmlNode(): string {
		return self::ELEMENT;
	}
}
