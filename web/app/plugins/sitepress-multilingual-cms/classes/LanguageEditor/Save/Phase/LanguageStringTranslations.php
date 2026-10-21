<?php

namespace WPML\LanguageEditor\Save\Phase;

class LanguageStringTranslations {

	const RECORD_TYPE = 'string-translations';

	public static function remaining( array $langs ) {
		global $wpdb;

		if ( ! $langs || ! self::tableExists() ) {
			return 0;
		}

		$in = wpml_prepare_in( $langs );

		$remaining = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}icl_string_translations WHERE language IN ({$in})"
		);

		return $remaining;
	}

	public static function deleteChunk( array $langs, $limit ) {
		global $wpdb;

		$limit = max( 1, (int) $limit );

		if ( ! $langs || ! self::tableExists() ) {
			return 0;
		}

		$in = wpml_prepare_in( $langs );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}icl_string_translations WHERE language IN ({$in}) LIMIT %d",
				$limit
			)
		);

		return max( 0, (int) $deleted );
	}

	private static function tableExists() {
		global $wpdb;

		if ( ! is_object( $wpdb ) ) {
			return false;
		}

		return (bool) $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'icl_string_translations' )
		);
	}
}
