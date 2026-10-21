<?php

namespace WPML\PB\TranslationJob;

class SourceLabels {

	const PRIORITY = PHP_INT_MAX;

	private static $depth = 0;

	private static $hooked = false;

	public static function run( callable $callback ) {
		if ( ! self::$hooked ) {
			add_filter( 'gettext_sitepress', [ self::class, 'untranslated' ], self::PRIORITY, 2 );
			add_filter( 'gettext_with_context_sitepress', [ self::class, 'untranslated' ], self::PRIORITY, 2 );
			self::$hooked = true;
		}

		++self::$depth;

		try {
			return $callback();
		} finally {
			--self::$depth;
		}
	}

	public static function isActive() {
		return self::$depth > 0;
	}

	public static function untranslated( $translation, $text ) {
		return self::$depth > 0 ? $text : $translation;
	}
}
