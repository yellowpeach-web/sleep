<?php


namespace WPML\Upgrade\Commands;

class AddReviewModeColumnToIclTranslateJob extends \WPML_Upgrade_Add_Column_To_Table {
	protected function get_table() {
		return 'icl_translate_job';
	}

	protected function get_column() {
		return 'review_mode';
	}

	protected function get_column_definition() {
		return 'VARCHAR(20) NULL';
	}
}
