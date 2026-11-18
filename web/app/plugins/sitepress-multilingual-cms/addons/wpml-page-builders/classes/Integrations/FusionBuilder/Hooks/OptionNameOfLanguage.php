<?php

namespace WPML\Compatibility\FusionBuilder\Hooks;

trait OptionNameOfLanguage {

	private function getOptionName( $language ) {
		return MultilingualOptions::LANGUAGE_OF_UNSUFFIXED_OPTION === $language
			? MultilingualOptions::OPTION_NAME
			: MultilingualOptions::OPTION_NAME . '_' . $language;
	}
}
