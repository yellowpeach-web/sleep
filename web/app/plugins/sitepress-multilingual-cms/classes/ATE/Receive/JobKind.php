<?php

namespace WPML\TM\ATE\Receive;

use WPML\TM\ATE\TranslateEverything\TermBundle;
use WPML\TM\ATE\TranslateEverything\UntranslatedTerms;

class JobKind {

	public static function isTaxonomyTerm( $wpmlJobId ) {
		$elementType = self::elementTypeOf( $wpmlJobId );

		return is_string( $elementType )
			&& ( TermBundle::ELEMENT_TYPE === $elementType || 0 === strpos( $elementType, UntranslatedTerms::ELEMENT_TYPE_PREFIX ) );
	}

	public static function isTermBundle( $wpmlJobId ) {
		return TermBundle::ELEMENT_TYPE === self::elementTypeOf( $wpmlJobId );
	}

	private static function elementTypeOf( $wpmlJobId ) {
		global $wpdb;

		$elementType = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT t.element_type
				FROM {$wpdb->prefix}icl_translate_job tj
				INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = tj.rid
				INNER JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = ts.translation_id
				WHERE tj.job_id = %d",
				(int) $wpmlJobId
			)
		);

		return is_string( $elementType ) ? $elementType : null;
	}
}
