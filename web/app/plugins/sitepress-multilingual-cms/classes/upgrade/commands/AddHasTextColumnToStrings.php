<?php

namespace WPML\Upgrade\Commands;

class AddHasTextColumnToStrings extends \WPML_Upgrade_Add_Column_To_Table {

	protected function get_table() {
		return 'icl_strings';
	}

	protected function get_column() {
		return 'has_text';
	}

	protected function get_column_definition() {
		return 'TINYINT(1) NOT NULL DEFAULT 1';
	}
}
