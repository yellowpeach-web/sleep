<?php

namespace WPML\WPSEO\RankMathSEO\EditScreen;

use WPML\WPSEO\Shared\EditScreen\BaseTemplatesLanguageHooks;

class TemplatesLanguageHooks extends BaseTemplatesLanguageHooks {

	const TEMPLATES_OPTION_NAME = \WPML\WPSEO\RankMathSEO\AuthorBase\Hooks::OPTION_NAME;

	protected function getTemplatesOptionName() {
		return self::TEMPLATES_OPTION_NAME;
	}

	protected function reloadTemplates() {
		rank_math()->settings->reset();
	}
}
