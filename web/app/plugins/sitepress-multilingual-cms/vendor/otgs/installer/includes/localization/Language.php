<?php

namespace OTGS\Installer\Localization;

class Language {

	const DEFAULT_LANGUAGE = 'en';

	public static function getCurrent() {
		global $sitepress;

		if ( $sitepress ) {
			return self::normalize( $sitepress->get_admin_language() );
		}

		$locale = function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();

		return self::normalize( $locale );
	}

	public static function normalize( $languageOrLocale ) {
		$language = strtolower( str_replace( '_', '-', trim( (string) $languageOrLocale ) ) );

		if ( $language === '' ) {
			return self::DEFAULT_LANGUAGE;
		}

		if ( preg_match( '/^pt-(br|pt)(?:-|$)/', $language, $matches ) ) {
			return 'pt-' . $matches[1];
		}

		if ( preg_match( '/^zh-(hans|cn|sg)(?:-|$)/', $language ) ) {
			return 'zh-hans';
		}

		if ( preg_match( '/^zh-(hant|tw|hk|mo)(?:-|$)/', $language ) ) {
			return 'zh-hant';
		}

		$parts = explode( '-', $language );

		return $parts[0];
	}

	public static function getValue( $translations, $language = null ) {
		if ( ! is_array( $translations ) ) {
			return is_string( $translations ) ? $translations : '';
		}

		$language = $language === null ? self::getCurrent() : self::normalize( $language );
		$normalizedTranslations = [];

		foreach ( $translations as $translationLanguage => $translation ) {
			$normalizedTranslations[ self::normalize( $translationLanguage ) ] = $translation;
		}

		if ( isset( $normalizedTranslations[ $language ] ) && $normalizedTranslations[ $language ] !== '' ) {
			return $normalizedTranslations[ $language ];
		}

		return isset( $normalizedTranslations[ self::DEFAULT_LANGUAGE ] )
			? $normalizedTranslations[ self::DEFAULT_LANGUAGE ]
			: '';
	}

}
