<?php

namespace WPML\WPSEO\YoastSEO\EditScreen;

use WPML\WPSEO\Shared\EditScreen\BaseTemplatesLanguageHooks;

class TemplatesLanguageHooks extends BaseTemplatesLanguageHooks {

	const TEMPLATES_OPTION_NAME = \WPML\WPSEO\YoastSEO\Presentation\Hooks::OPTION_KEY;

	protected function getTemplatesOptionName() {
		return self::TEMPLATES_OPTION_NAME;
	}

	protected function reloadTemplates() {
		\WPSEO_Options::clear_cache();
	}
}
