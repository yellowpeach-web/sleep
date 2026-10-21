<?php

namespace WPML\Upgrade\Commands;

class WidenTranslateJobManagerIdColumn extends WidenColumn {

	const TABLE  = 'icl_translate_job';
	const COLUMN = 'manager_id';

	const COLUMN_DEFINITION = 'BIGINT UNSIGNED NOT NULL';

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
