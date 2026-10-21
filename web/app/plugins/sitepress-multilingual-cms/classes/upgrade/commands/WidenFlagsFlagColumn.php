<?php

namespace WPML\Upgrade\Commands;

class WidenFlagsFlagColumn extends WidenColumn {

	const TABLE  = 'icl_flags';
	const COLUMN = 'flag';

	const COLUMN_DEFINITION = 'VARCHAR( 255 ) NOT NULL';

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
