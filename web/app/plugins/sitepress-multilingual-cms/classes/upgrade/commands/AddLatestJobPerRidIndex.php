<?php

namespace WPML\Upgrade\Commands;

class AddLatestJobPerRidIndex extends AddIndexToTable {

	protected function get_table() {
		return 'icl_translate_job';
	}

	protected function get_index() {
		return 'rid_job_id';
	}

	protected function get_index_definition() {
		return '( `rid`, `job_id` )';
	}
}
