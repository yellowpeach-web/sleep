<?php

namespace WPML\Upgrade\Commands;

class WidenTranslateJobTitleColumn extends WidenColumn {

	const TABLE  = 'icl_translate_job';
	const COLUMN = 'title';

	const COLUMN_DEFINITION = 'TEXT NULL';

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
