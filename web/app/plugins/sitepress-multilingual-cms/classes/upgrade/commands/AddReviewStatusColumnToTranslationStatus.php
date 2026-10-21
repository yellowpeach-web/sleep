<?php

namespace WPML\TM\Upgrade\Commands;

use WPML\Upgrade\TranslationStatusSchema;

class AddReviewStatusColumnToTranslationStatus extends \WPML_Upgrade_Add_Column_To_Table {
	protected function get_table() {
		return TranslationStatusSchema::TABLE;
	}

	protected function get_column() {
		return 'review_status';
	}

	protected function get_column_definition() {
		return TranslationStatusSchema::COLUMNS['review_status'];
	}
}
