<?php


namespace WPML\Upgrade\Commands;


class AddWpCodeColumnToLanguagePresets extends \WPML_Upgrade_Add_Column_To_Table implements \IWPML_Pre_Setup_Upgrade_Command {

	protected function get_table() {
		return 'icl_language_presets';
	}

	protected function get_column() {
		return 'wp_code';
	}

	protected function get_column_definition() {
		return 'VARCHAR(35) NULL DEFAULT NULL';
	}
}
