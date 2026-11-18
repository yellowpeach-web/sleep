<?php

namespace ACFML\Options;

class CurrentLanguageHooks implements \IWPML_Action {

	const ACF_CURRENT_LANGUAGE_SETTING = 'current_language';
	const ACF_SCRIPTS_LANGUAGE         = 'language';

	public function add_hooks() {
		add_action( 'wpml_language_has_switched', [ $this, 'followSwitchedLanguage' ] );
		add_action( 'wp_loaded', [ $this, 'followRestoredLanguage' ], 11 );
	}

	public function followRestoredLanguage() {
		$this->followSwitchedLanguage();

		acf_localize_data( [ self::ACF_SCRIPTS_LANGUAGE => apply_filters( 'wpml_current_language', null ) ] );
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
