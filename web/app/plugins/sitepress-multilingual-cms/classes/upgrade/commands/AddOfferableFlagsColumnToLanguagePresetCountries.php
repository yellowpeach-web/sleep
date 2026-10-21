<?php


namespace WPML\Upgrade\Commands;


class AddOfferableFlagsColumnToLanguagePresetCountries extends \WPML_Upgrade_Add_Column_To_Table implements \IWPML_Pre_Setup_Upgrade_Command {

	protected function get_table() {
		return 'icl_language_preset_countries';
	}

	protected function get_column() {
		return 'offerable_flags';
	}

	protected function get_column_definition() {
		return 'VARCHAR(191) NULL DEFAULT NULL';
	}
}
