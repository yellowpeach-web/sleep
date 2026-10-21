<?php


namespace WPML\Upgrade\Commands;


class AddVisibilityTierColumnToLanguagePresets extends \WPML_Upgrade_Add_Column_To_Table implements \IWPML_Pre_Setup_Upgrade_Command {

	protected function get_table() {
		return 'icl_language_presets';
	}

	protected function get_column() {
		return 'visibility_tier';
	}

	protected function get_column_definition() {
		return "VARCHAR(16) NOT NULL DEFAULT 'all'";
	}


}
