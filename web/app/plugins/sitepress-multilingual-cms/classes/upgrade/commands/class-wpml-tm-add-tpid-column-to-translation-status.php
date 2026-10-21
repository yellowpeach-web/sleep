<?php

use WPML\Upgrade\TranslationStatusSchema;

class WPML_TM_Add_TP_ID_Column_To_Translation_Status extends WPML_Upgrade_Run_All {

	private $upgrade_schema;

	public function __construct( array $args ) {
		$this->upgrade_schema = $args[0];
	}

	protected function run() {
		$this->upgrade_schema->ensure_columns(
			TranslationStatusSchema::TABLE,
			[ 'tp_id' => TranslationStatusSchema::COLUMNS['tp_id'] ]
		);

		$this->result = true;

		return $this->result;
	}
}
