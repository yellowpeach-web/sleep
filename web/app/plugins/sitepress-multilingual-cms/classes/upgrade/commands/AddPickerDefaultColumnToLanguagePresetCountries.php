<?php


namespace WPML\Upgrade\Commands;


class AddPickerDefaultColumnToLanguagePresetCountries extends \WPML_Upgrade_Add_Column_To_Table implements \IWPML_Pre_Setup_Upgrade_Command {

	protected function get_table() {
		return 'icl_language_preset_countries';
	}

	protected function get_column() {
		return 'picker_default';
	}

	protected function get_column_definition() {
		return 'TINYINT(1) NOT NULL DEFAULT 0';
	}
}
