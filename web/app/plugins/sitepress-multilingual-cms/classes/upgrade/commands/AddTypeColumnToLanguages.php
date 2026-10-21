<?php


namespace WPML\Upgrade\Commands;


class AddTypeColumnToLanguages extends \WPML_Upgrade_Add_Column_To_Table implements \IWPML_Pre_Setup_Upgrade_Command {

	protected function get_table() {
		return 'icl_languages';
	}

	protected function get_column() {
		return 'type';
	}

	protected function get_column_definition() {
		return 'VARCHAR(20) NULL DEFAULT NULL';
	}

}
