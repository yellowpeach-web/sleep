<?php

namespace WPML\TM\Settings;

use WPML\Collect\Support\Collection;

class GetNumberOfPostsForCustomField {

	public function run( Collection $data, \wpdb $wpdb ) {
		$customFields = $data->get( 'customFields', [] );

		if ( empty( $customFields ) ) {
			return 0;
		}

		$fieldsIn = wpml_prepare_in( $customFields );

		return (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT pm.post_id)
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->prefix}icl_translations orig
				ON orig.element_id = pm.post_id
				AND orig.element_type LIKE 'post_%'
				AND orig.source_language_code IS NULL
			WHERE pm.meta_key IN ({$fieldsIn})
			  AND pm.meta_key <> ''
			  AND EXISTS (
			      SELECT 1 FROM {$wpdb->prefix}icl_translations tr
			      WHERE tr.trid = orig.trid
			        AND tr.source_language_code IS NOT NULL
			  )"
		);
	}
}
