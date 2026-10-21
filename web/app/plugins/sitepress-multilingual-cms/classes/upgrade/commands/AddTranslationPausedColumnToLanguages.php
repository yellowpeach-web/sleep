<?php


namespace WPML\Upgrade\Commands;


class AddTranslationPausedColumnToLanguages extends \WPML_Upgrade_Add_Column_To_Table implements \IWPML_Pre_Setup_Upgrade_Command {

	protected function get_table() {
		return 'icl_languages';
	}

	protected function get_column() {
		return 'translation_paused';
	}

	protected function get_column_definition() {
		return 'TINYINT(1) NOT NULL DEFAULT 0';
	}

}
