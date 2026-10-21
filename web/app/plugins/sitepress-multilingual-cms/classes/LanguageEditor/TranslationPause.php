<?php

namespace WPML\LanguageEditor;

class TranslationPause {

	const COLUMN = 'translation_paused';

	const ERROR_NOT_AVAILABLE = 'not_available';

	const ERROR_UNKNOWN_LANGUAGE = 'unknown_language';

	const ERROR_DEFAULT_LANGUAGE = 'default_language';

	private static $paused = null;

	private static $hasColumn = null;

	public static function pausedCodes() {
		global $wpdb;

		if ( null !== self::$paused ) {
			return self::$paused;
		}

		if ( ! self::hasColumn() ) {
			self::$paused = [];

			return self::$paused;
		}

		$codes = (array) $wpdb->get_col(
			"SELECT code FROM {$wpdb->prefix}icl_languages WHERE translation_paused = 1"
		);

		self::$paused = array_values(
			array_filter(
				array_map( 'strval', $codes ),
				function ( $code ) {
					return '' !== $code;
				}
			)
		);

		return self::$paused;
	}

	public static function isPaused( $code ) {
		return in_array( (string) $code, self::pausedCodes(), true );
	}

	public static function filterTranslatable( array $codes ) {
		if ( ! $codes ) {
			return [];
		}

		$paused = self::pausedCodes();

		if ( ! $paused ) {
			return array_values( $codes );
		}

		return array_values(
			array_filter(
				$codes,
				function ( $code ) use ( $paused ) {
					return ! in_array( (string) $code, $paused, true );
				}
			)
		);
	}

	public static function setPaused( $code, $paused ) {
		global $wpdb;

		$code = (string) $code;

		if ( '' === $code ) {
			return self::ERROR_UNKNOWN_LANGUAGE;
		}

		if ( ! self::hasColumn() ) {
			return self::ERROR_NOT_AVAILABLE;
		}

		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT code FROM {$wpdb->prefix}icl_languages WHERE code = %s AND active = 1",
				$code
			)
		);

		if ( null === $found || '' === $found ) {
			return self::ERROR_UNKNOWN_LANGUAGE;
		}

		if ( $paused && $code === self::defaultCode() ) {
			return self::ERROR_DEFAULT_LANGUAGE;
		}

		$wpdb->update(
			$wpdb->prefix . 'icl_languages',
			[ self::COLUMN => $paused ? 1 : 0 ],
			[ 'code' => $code ],
			[ '%d' ],
			[ '%s' ]
		);

		self::resetCache();

		return '';
	}

	private static function defaultCode() {
		global $sitepress;

		return is_object( $sitepress ) && method_exists( $sitepress, 'get_default_language' )
			? (string) $sitepress->get_default_language()
			: '';
	}

	public static function resetCache() {
		self::$paused    = null;
		self::$hasColumn = null;
	}

	private static function hasColumn() {
		global $wpdb;

		if ( null !== self::$hasColumn ) {
			return self::$hasColumn;
		}

		if ( ! is_object( $wpdb ) ) {
			self::$hasColumn = false;

			return self::$hasColumn;
		}

		self::$hasColumn = (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$wpdb->prefix}icl_languages` LIKE %s",
				self::COLUMN
			)
		);

		return self::$hasColumn;
	}
}
