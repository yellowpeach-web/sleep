<?php

namespace WPML\Upgrade\Commands;

class WidenTranslateFieldTypeColumn extends WidenColumn {

	const TABLE  = 'icl_translate';
	const COLUMN = 'field_type';

	const COLUMN_DEFINITION = 'VARCHAR( 263 ) NOT NULL';

	protected function get_table() {
		return self::TABLE;
	}

	protected function get_column() {
		return self::COLUMN;
	}

	protected function get_column_definition() {
		return self::COLUMN_DEFINITION;
	}
}
