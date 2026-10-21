<?php

use WPML\Upgrade\TranslationStatusSchema;

class WPML_TM_Add_TP_Revision_And_TS_Status_Columns_To_Translation_Status extends WPML_Upgrade_Run_All {

	private $upgrade_schema;

	public function __construct( array $args ) {
		$this->upgrade_schema = $args[0];
	}

	protected function run() {
		$this->upgrade_schema->ensure_columns(
			TranslationStatusSchema::TABLE,
			[
				'tp_revision' => TranslationStatusSchema::COLUMNS['tp_revision'],
				'ts_status'   => TranslationStatusSchema::COLUMNS['ts_status'],
			]
		);

		$this->result = true;

		return $this->result;
	}
}
