<?php

namespace WPML\Upgrade\Commands;

use WPML\Utilities\KeyedLock;
use function WPML\Container\make;

class AddFieldTypeJobIdIndex extends AddIndexToTable {

	const LOCK_NAME = 'wpml_add_field_type_job_id_index';
	const LOCK_TIMEOUT = 600;
	protected function run() {
		$lock = make( KeyedLock::class, [ ':name' => self::LOCK_NAME ] );
		if ( ! $lock->create( null, self::LOCK_TIMEOUT ) ) {
			return $this->result = false;
		}
		try {
			return parent::run();
		} finally {
			$lock->release();
		}
	}
	protected function get_table() {
		return 'icl_translate';
	}

	protected function get_index() {
		return 'field_type_job_id';
	}

	protected function get_index_definition() {
		return '( `field_type`(64), `job_id` )';
	}
}
