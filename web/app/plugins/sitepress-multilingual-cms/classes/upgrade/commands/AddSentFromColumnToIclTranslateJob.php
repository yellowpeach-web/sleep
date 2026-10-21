<?php


namespace WPML\Upgrade\Commands;

class AddSentFromColumnToIclTranslateJob extends \WPML_Upgrade_Add_Column_To_Table {
	protected function get_table() {
		return 'icl_translate_job';
	}

	protected function get_column() {
		return 'sent_from';
	}

	protected function get_column_definition() {
		return 'TINYINT UNSIGNED NULL';
	}
}
