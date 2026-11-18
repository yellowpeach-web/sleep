<?php

namespace WPML\Translation;

use WPML\Core\Component\Translation\Application\Service\PreviousState\PreviousStateService;
use WPML\Core\Component\Translation\Domain\CompletedTranslationDetector;

class PreviousStateServiceFactory {
	private static $instance = null;

	private static $detector = null;

	public static function create(): PreviousStateService {
		if ( self::$instance === null ) {
			self::$instance = self::createNewInstance();
		}

		return self::$instance;
	}

	public static function setService( PreviousStateService $instance ) {
		self::$instance = $instance;
	}

	public static function completedTranslationDetector(): CompletedTranslationDetector {
		if ( self::$detector === null ) {
			global $wpml_dic;

			self::$detector = $wpml_dic->make( CompletedTranslationDetector::class );
		}

		return self::$detector;
	}

	public static function setCompletedTranslationDetector( ?CompletedTranslationDetector $detector ) {
		self::$detector = $detector;
	}

	private static function createNewInstance(): PreviousStateService {
		global $wpml_dic;

		return $wpml_dic->make( PreviousStateService::class );
	}
}
