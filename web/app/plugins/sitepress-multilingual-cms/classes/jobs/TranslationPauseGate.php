<?php

namespace WPML\TM\Jobs;

use WPML\LanguageEditor\TranslationPause;

class TranslationPauseGate {

	public static function refusesRid( $rid ) {
		global $wpdb;

		if ( ! $rid || ! TranslationPause::pausedCodes() ) {
			return false;
		}

		$language = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT t.language_code
				   FROM {$wpdb->prefix}icl_translation_status s
				   INNER JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = s.translation_id
				  WHERE s.rid = %d",
				$rid
			)
		);

		return null !== $language && TranslationPause::isPaused( (string) $language );
	}
}
