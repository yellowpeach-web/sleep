<?php

use WPML\Upgrade\TranslationStatusSchema;

class WPML_Add_UUID_Column_To_Translation_Status extends WPML_Upgrade_Add_Column_To_Table {

	protected function get_table() {
		return TranslationStatusSchema::TABLE;
	}

	protected function get_column() {
		return 'uuid';
	}

	protected function get_column_definition() {
		return TranslationStatusSchema::COLUMNS['uuid'];
	}
}
