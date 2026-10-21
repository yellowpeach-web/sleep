<?php


namespace WPML\Upgrade\Commands;


class AddLanguageColumnToLanguagePresets extends \WPML_Upgrade_Add_Column_To_Table implements \IWPML_Pre_Setup_Upgrade_Command {

	protected function get_table() {
		return 'icl_language_presets';
	}

	protected function get_column() {
		return 'language';
	}

	protected function get_column_definition() {
		return "VARCHAR(40) NOT NULL DEFAULT ''";
	}

}
