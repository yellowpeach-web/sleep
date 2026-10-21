<?php

namespace WPML\Languages;

class PerRequestLanguageMemos {

	const MEMOS = array(
		'WPML\LanguageEditor\LanguageCodeResolution',
		'WPML\LanguageEditor\TranslationPause',
		'WPML\Languages\RemovedLanguages',
		'WPML\Troubleshooting\OrphanLanguageCodes',
	);

	public static function addHooks() {
		add_action( 'switch_blog', array( __CLASS__, 'resetAll' ), -PHP_INT_MAX, 0 );
	}

	public static function resetAll() {
		foreach ( self::MEMOS as $class ) {
			if ( class_exists( $class, false ) ) {
				call_user_func( array( $class, 'resetCache' ) );
			}
		}
	}
}
