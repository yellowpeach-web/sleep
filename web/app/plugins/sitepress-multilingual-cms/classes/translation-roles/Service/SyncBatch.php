<?php

namespace WPML\TranslationRoles\Service;

class SyncBatch {

	public static function run( callable $work ) {
		\WPML_TM_AMS_Synchronize_Actions::openBatch();
		try {
			return $work();
		} finally {
			\WPML_TM_AMS_Synchronize_Actions::closeBatch();
		}
	}
}
