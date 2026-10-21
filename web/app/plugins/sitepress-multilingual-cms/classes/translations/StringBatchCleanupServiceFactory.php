<?php

namespace WPML\Translation;

use WPML\Core\SharedKernel\Component\String\Application\Service\StringBatchCleanupService;

class StringBatchCleanupServiceFactory {
	private static $instance = null;

	public static function create(): StringBatchCleanupService {
		if ( self::$instance === null ) {
			self::$instance = self::createNewInstance();
		}

		return self::$instance;
	}

	public static function setService( StringBatchCleanupService $instance ) {
		self::$instance = $instance;
	}

	private static function createNewInstance(): StringBatchCleanupService {
		global $wpml_dic;

		return $wpml_dic->make( StringBatchCleanupService::class );
	}
}
