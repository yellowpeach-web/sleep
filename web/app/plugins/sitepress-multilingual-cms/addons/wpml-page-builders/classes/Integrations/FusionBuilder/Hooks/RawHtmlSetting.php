<?php

namespace WPML\Compatibility\FusionBuilder\Hooks;

class RawHtmlSetting extends \WPML\Compatibility\BaseRawHtmlShortcodeSetting {

	const SHORTCODE = 'fusion_code';

	protected function getCodeShortcodes(): array {
		return [ self::SHORTCODE ];
	}
}
