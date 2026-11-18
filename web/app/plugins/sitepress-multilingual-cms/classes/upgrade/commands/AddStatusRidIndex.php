<?php

namespace WPML\Upgrade\Commands;

use WPML\Utilities\KeyedLock;
use function WPML\Container\make;

class AddStatusRidIndex extends AddIndexToTable {

	const LOCK_NAME = 'wpml_add_status_rid_index';
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
		return 'icl_translation_status';
	}

	protected function get_index() {
		return 'status_rid';
	}

	protected function get_index_definition() {
		return '( `status`, `rid` )';
	}
}
