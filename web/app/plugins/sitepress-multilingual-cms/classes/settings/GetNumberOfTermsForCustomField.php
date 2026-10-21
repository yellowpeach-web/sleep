<?php

namespace WPML\TM\Settings;

use WPML\Collect\Support\Collection;

class GetNumberOfTermsForCustomField {

	public function run( Collection $data, \wpdb $wpdb ) {
		$customFields = array_values( array_filter( array_map( 'strval', (array) $data->get( 'customFields', [] ) ) ) );

		if ( ! $customFields ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT tm.term_id)
				FROM {$wpdb->termmeta} tm
				INNER JOIN {$wpdb->term_taxonomy} tt
					ON tt.term_id = tm.term_id
				INNER JOIN {$wpdb->prefix}icl_translations orig
					ON orig.element_id = tt.term_taxonomy_id
					AND orig.element_type = CONCAT('tax_', tt.taxonomy)
					AND orig.source_language_code IS NULL
				WHERE tm.meta_key IN (" . implode( ', ', array_fill( 0, count( $customFields ), '%s' ) ) . ")
				  AND tm.meta_key <> ''
				  AND EXISTS (
				      SELECT 1 FROM {$wpdb->prefix}icl_translations tr
				      WHERE tr.trid = orig.trid
				        AND tr.source_language_code IS NOT NULL
				  )",
				...$customFields
			)
		);
	}
}
