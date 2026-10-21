<?php

namespace WPML\Upgrade\Commands;

class AddIsRtlColumnToLanguages extends \WPML_Upgrade_Add_Column_To_Table implements \IWPML_Pre_Setup_Upgrade_Command {

	protected function get_table() {
		return 'icl_languages';
	}

	protected function get_column() {
		return 'is_rtl';
	}

	protected function get_column_definition() {
		return 'TINYINT(1) NULL DEFAULT NULL';
	}
}
