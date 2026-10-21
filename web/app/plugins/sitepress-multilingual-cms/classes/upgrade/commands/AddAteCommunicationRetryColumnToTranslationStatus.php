<?php

namespace WPML\TM\Upgrade\Commands;

use WPML\Upgrade\TranslationStatusSchema;

class AddAteCommunicationRetryColumnToTranslationStatus extends \WPML_Upgrade_Add_Column_To_Table {
	protected function get_table() {
		return TranslationStatusSchema::TABLE;
	}

	protected function get_column() {
		return 'ate_comm_retry_count';
	}

	protected function get_column_definition() {
		return TranslationStatusSchema::COLUMNS['ate_comm_retry_count'];
	}
}
