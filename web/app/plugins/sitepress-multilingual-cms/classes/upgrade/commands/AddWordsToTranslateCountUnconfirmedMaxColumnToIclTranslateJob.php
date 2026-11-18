<?php


namespace WPML\Upgrade\Commands;

class AddWordsToTranslateCountUnconfirmedMaxColumnToIclTranslateJob extends \WPML_Upgrade_Add_Column_To_Table {
	protected function get_table() {
		return 'icl_translate_job';
	}

	protected function get_column() {
		return 'wpml_words_to_translate_count_unconfirmed_max';
	}

	protected function get_column_definition() {
		return 'INT(11) UNSIGNED NULL';
	}
}
