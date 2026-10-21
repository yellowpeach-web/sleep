<?php

namespace WPML\Upgrade\Commands;

class AddDisplayCodeColumnToLanguages extends \WPML_Upgrade_Add_Column_To_Table implements \IWPML_Pre_Setup_Upgrade_Command {

	protected function get_table() {
		return 'icl_languages';
	}

	protected function get_column() {
		return 'display_code';
	}

	protected function get_column_definition() {
		return 'VARCHAR(40) NULL DEFAULT NULL';
	}
}
