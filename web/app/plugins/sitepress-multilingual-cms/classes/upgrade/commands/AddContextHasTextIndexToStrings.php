<?php

namespace WPML\Upgrade\Commands;

class AddContextHasTextIndexToStrings extends AddIndexToTable {

	protected function get_table() {
		return 'icl_strings';
	}

	protected function get_index() {
		return 'context_has_text';
	}

	protected function get_index_definition() {
		return '( `context`, `has_text` )';
	}
}
