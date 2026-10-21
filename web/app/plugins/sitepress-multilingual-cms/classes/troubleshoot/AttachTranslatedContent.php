<?php

namespace WPML\Troubleshooting;

use WPML\Posts\TranslatedContentOfLanguages;

class AttachTranslatedContent {

	public static function run( $source, $target, $dryRun = false ) {
		global $wpdb;

		$translations = $wpdb->prefix . 'icl_translations';

		$collidingRows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.translation_id AS source_translation_id, t.element_type,
				        t.element_id AS source_element_id, t.trid,
				        x.translation_id AS target_translation_id, x.element_id AS target_element_id
				 FROM {$translations} t
				 INNER JOIN {$translations} x ON x.trid = t.trid AND x.language_code = %s
				 WHERE t.language_code = %s",
				$target,
				$source
			)
		);

		if ( $dryRun ) {
			return array(
				'content'            => 0,
				'sources'            => 0,
				'stringTranslations' => 0,
				'strings'            => 0,
				'skipped'            => 0,
				'collidingRows'      => $collidingRows,
			);
		}

		$content = (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$translations} t
				 LEFT JOIN {$translations} x ON x.trid = t.trid AND x.language_code = %s
				 SET t.language_code = %s
				 WHERE t.language_code = %s AND x.translation_id IS NULL",
				$target,
				$target,
				$source
			)
		);

		$sources = (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$translations} t
				 LEFT JOIN {$translations} s ON s.trid = t.trid AND s.language_code = %s
				 SET t.source_language_code = %s
				 WHERE t.source_language_code = %s AND s.translation_id IS NULL",
				$source,
				$target,
				$source
			)
		);

		$stringTranslations = (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_string_translations st
				 LEFT JOIN {$wpdb->prefix}icl_string_translations x
					ON x.string_id = st.string_id AND x.language = %s
				 SET st.language = %s
				 WHERE st.language = %s AND x.id IS NULL",
				$target,
				$target,
				$source
			)
		);

		$strings = (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_strings SET language = %s WHERE language = %s",
				$target,
				$source
			)
		);

		self::flushCaches();

		\WPML\Comments\Counts\Store::markOutOfDateIfOn();

		$left = TranslatedContentOfLanguages::counts( array( $source ) );

		return array(
			'content'            => $content,
			'sources'            => $sources,
			'stringTranslations' => $stringTranslations,
			'strings'            => $strings,
			'skipped'            => (int) $left['total'],
			'collidingRows'      => $collidingRows,
		);
	}

	private static function flushCaches() {
		if ( function_exists( 'icl_cache_clear' ) ) {
			icl_cache_clear();
		}
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}
}
