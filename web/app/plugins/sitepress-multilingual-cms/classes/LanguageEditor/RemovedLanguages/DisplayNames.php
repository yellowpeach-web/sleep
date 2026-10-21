<?php

namespace WPML\LanguageEditor\RemovedLanguages;

class DisplayNames {

	public static function forCodes( array $codes ) {
		global $wpdb;

		$names = array();
		foreach ( $codes as $code ) {
			$code = (string) $code;
			if ( '' !== $code && ! isset( $names[ $code ] ) ) {
				$names[ $code ] = $code;
			}
		}

		if ( ! $names || ! is_object( $wpdb ) ) {
			return $names;
		}

		$list         = array_keys( $names );
		$placeholders = implode( ',', array_fill( 0, count( $list ), '%s' ) );

		$english = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT code, english_name FROM {$wpdb->prefix}icl_languages WHERE code IN ({$placeholders})",
				...$list
			)
		);

		foreach ( (array) $english as $row ) {
			$code = (string) $row->code;
			$name = null !== $row->english_name ? (string) $row->english_name : '';
			if ( isset( $names[ $code ] ) && '' !== $name ) {
				$names[ $code ] = $name;
			}
		}

		$translated = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT language_code, name FROM {$wpdb->prefix}icl_languages_translations
				 WHERE display_language_code = %s AND language_code IN ({$placeholders})",
				self::adminLanguage(),
				...$list
			)
		);

		foreach ( (array) $translated as $row ) {
			$code = (string) $row->language_code;
			$name = null !== $row->name ? (string) $row->name : '';
			if ( isset( $names[ $code ] ) && '' !== $name ) {
				$names[ $code ] = $name;
			}
		}

		return $names;
	}

	public static function forCode( $code ) {
		$names = self::forCodes( array( $code ) );

		return isset( $names[ (string) $code ] ) ? $names[ (string) $code ] : (string) $code;
	}

	private static function adminLanguage() {
		$code = function_exists( 'wpml_get_current_language' ) ? (string) wpml_get_current_language() : '';

		return '' !== $code ? $code : 'en';
	}
}
