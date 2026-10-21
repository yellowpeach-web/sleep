<?php

namespace ACFML\FieldGroup;

use function WPML\Container\make;

class AttachedTerms {

	public static function countAffected( array $fieldNames ) {
		$fieldNames = array_values(
			array_filter(
				array_unique( $fieldNames ),
				function ( $name ) {
					return '' !== (string) $name;
				}
			)
		);

		if ( ! $fieldNames ) {
			return 0;
		}

		global $wpdb;

		$fieldsIn = wpml_prepare_in( $fieldNames );

		$count = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT tm.term_id)
			FROM {$wpdb->termmeta} tm
			INNER JOIN {$wpdb->term_taxonomy} tt
				ON tt.term_id = tm.term_id
			INNER JOIN {$wpdb->prefix}icl_translations orig
				ON orig.element_id = tt.term_taxonomy_id
				AND orig.element_type LIKE 'tax_%'
				AND orig.source_language_code IS NULL
			WHERE tm.meta_key IN ({$fieldsIn})
			  AND tm.meta_key <> ''
			  AND EXISTS (
			      SELECT 1 FROM {$wpdb->prefix}icl_translations tr
			      WHERE tr.trid = orig.trid
			        AND tr.source_language_code IS NOT NULL
			  )"
		);

		return $count + count( make( SubfieldRows::class )->termIdsForFieldNames( $fieldNames ) );
	}
}
