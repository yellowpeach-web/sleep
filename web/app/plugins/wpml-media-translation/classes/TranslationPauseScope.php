<?php

namespace WPML\Media;

class TranslationPauseScope {

	const CORE_READ_MODEL = '\WPML\LanguageEditor\TranslationPause';

	const CORE_DELIVERY_READER = '\WPML_Save_Translation_Data_Action';

	const CORE_DELIVERY_READER_METHOD = 'is_delivery_window_open';

	const LEGACY_DELIVERY_FLAG = 'skip_sitepress_actions';

	public static function creationPaused( $code ) {
		if ( self::deliveryWindowOpen() ) {
			return false;
		}

		if ( ! class_exists( self::CORE_READ_MODEL ) ) {
			return false;
		}

		return \WPML\LanguageEditor\TranslationPause::isPaused( $code );
	}

	private static function deliveryWindowOpen() {
		$reader = array( static::CORE_DELIVERY_READER, static::CORE_DELIVERY_READER_METHOD );

		if ( is_callable( $reader ) ) {
			return (bool) call_user_func( $reader );
		}

		return array_key_exists( self::LEGACY_DELIVERY_FLAG, $_POST );
	}
}
