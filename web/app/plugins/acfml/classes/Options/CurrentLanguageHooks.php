<?php

namespace ACFML\Options;

class CurrentLanguageHooks implements \IWPML_Action {

	const ACF_CURRENT_LANGUAGE_SETTING = 'current_language';

	public function add_hooks() {
		add_action( 'wpml_language_has_switched', [ $this, 'followSwitchedLanguage' ] );
	}

	public function followSwitchedLanguage() {
		if ( ! $this->isLanguageSuffixEnabled() ) {
			return;
		}

		acf_update_setting( self::ACF_CURRENT_LANGUAGE_SETTING, apply_filters( 'wpml_current_language', null ) );
	}

	private function isLanguageSuffixEnabled() {
		return (bool) acf_raw_setting( self::ACF_CURRENT_LANGUAGE_SETTING );
	}
}
