<?php

namespace WPML\EditHistory;

class EditedTranslations {

	private $wpdb;

	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function translatedPostIds(): array {
		$wpdb = $this->wpdb;

		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT subject_id FROM {$wpdb->prefix}icl_edit_state
					WHERE subject_kind = %s AND head_entry_id > base_entry_id",
					Subject::POST
				)
			)
		);
	}

	public function addToDashboard( $data ) {
		if ( is_array( $data ) ) {
			$data['clientEdits'] = $this->translatedPostIds();
		}

		return $data;
	}
}
