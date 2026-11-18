<?php

namespace WPML\PB\BeaverBuilder\Hooks;

class RawHtmlSetting extends \WPML\Compatibility\BaseRawHtmlNodesSetting {

	const MODULE = 'html';

	protected function getNodesToTranslateFilter(): string {
		return 'wpml_beaver_builder_modules_to_translate';
	}

	protected function getRawHtmlNode(): string {
		return self::MODULE;
	}
}
