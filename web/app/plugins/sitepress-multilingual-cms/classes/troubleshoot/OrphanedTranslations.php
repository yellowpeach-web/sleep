<?php

namespace WPML\Troubleshooting;

use WPML\Posts\TranslatedContentOfLanguages;

class OrphanedTranslations {

	public static function counts() {
		$total      = 0;
		$byLanguage = array();

		foreach ( self::inactiveCodes() as $code ) {
			$counts = TranslatedContentOfLanguages::counts( array( $code ) );
			if ( $counts['total'] < 1 ) {
				continue;
			}

			$total               += $counts['total'];
			$byLanguage[ $code ]  = array(
				'name'  => self::languageName( $code ),
				'total' => $counts['total'],
				'types' => $counts['types'],
			);
		}

		return array(
			'total'      => $total,
			'byLanguage' => $byLanguage,
		);
	}

	public static function inactiveCodes() {
		global $wpdb;

		return array_values(
			(array) $wpdb->get_col(
				"SELECT code FROM {$wpdb->prefix}icl_languages WHERE active <> 1"
			)
		);
	}

	private static function languageName( $code ) {
		global $wpdb;

		$adminLanguage = function_exists( 'wpml_get_current_language' ) ? wpml_get_current_language() : 'en';

		$name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT name FROM {$wpdb->prefix}icl_languages_translations
				 WHERE language_code = %s AND display_language_code = %s",
				$code,
				$adminLanguage
			)
		);

		if ( ! $name ) {
			$name = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT english_name FROM {$wpdb->prefix}icl_languages WHERE code = %s",
					$code
				)
			);
		}

		return $name ? (string) $name : (string) $code;
	}
}
