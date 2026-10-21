<?php

namespace WPML\LanguageEditor;

class Cache {

	public static function flush( $oldActiveLanguages = null ) {
		global $sitepress;

		if ( function_exists( 'wpml_reload_active_languages_setting' ) ) {
			wpml_reload_active_languages_setting( true );
		}

		if ( function_exists( 'icl_cache_clear' ) ) {
			icl_cache_clear();
		}

		if ( $sitepress ) {
			$sitepress->get_language_name_cache()->clear();
		}

		if ( class_exists( \WPML\TM\API\ATE\CachedLanguageMappings::class ) ) {
			\WPML\TM\API\ATE\CachedLanguageMappings::clearCache();
		}

		do_action( 'icl_update_active_languages' );
		do_action( 'wpml_update_active_languages', $oldActiveLanguages );
	}
}
