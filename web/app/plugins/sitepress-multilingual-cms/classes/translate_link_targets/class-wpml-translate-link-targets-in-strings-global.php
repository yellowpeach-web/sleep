<?php

class WPML_Translate_Link_Targets_In_Strings_Global extends WPML_Translate_Link_Targets_In_Strings {

	protected function get_contents_with_links_needing_fix( $start_id = 0, $count = 0 ) {
		$wpdb = $this->wpdb;

		if ( $count > 0 ) {
			$this->content_to_fix = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id as element_id, language as language_code FROM {$wpdb->prefix}icl_string_translations WHERE id >= %d AND status = %d ORDER BY id LIMIT %d",
					$start_id,
					ICL_TM_COMPLETE,
					$count
				)
			);

			return;
		}

		$this->content_to_fix = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id as element_id, language as language_code FROM {$wpdb->prefix}icl_string_translations WHERE id >= %d AND status = %d ORDER BY id",
				$start_id,
				ICL_TM_COMPLETE
			)
		);
	}

	public function get_number_to_be_fixed( $start_id = 0, $limit = 0 ) {
		if ( $limit > 0 ) {
			$rows = $this->wpdb->get_col(
				$this->wpdb->prepare(
					"SELECT id FROM {$this->wpdb->prefix}icl_string_translations WHERE id >= %d AND status = %d LIMIT %d",
					$start_id,
					ICL_TM_COMPLETE,
					$limit
				)
			);

			return is_array( $rows ) ? count( $rows ) : 0;
		}

		return $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(id) FROM {$this->wpdb->prefix}icl_string_translations WHERE id >= %d AND status = %d",
				$start_id,
			ICL_TM_COMPLETE)
		);
	}

}
