<?php

namespace WPML\Language;

require_once __DIR__ . '/../../inc/constants-since-5-0.php';

use WPML\TM\Settings\RequestSettings;

class ActiveLanguagesReadModel {

	private static $cache = null;

	private static $invalidating = false;

	public static function cache(): \icl_cache {
		if ( null === self::$cache ) {
			self::ensureCacheApi();

			$cache      = null;
			$classifier = new CacheKeyClassifier(
				function () use ( &$cache ) {
					$settings = RequestSettings::load();
					$active   = isset( $settings['active_languages'] )
						? (array) $settings['active_languages']
						: array();

					if ( $cache instanceof \icl_cache ) {
						$supported = $cache->get( CacheKeyClassifier::SUPPORTED_LANGUAGE_CODES_KEY );
						if ( is_array( $supported ) && $supported ) {
							return array_map( 'strval', array_keys( $supported ) );
						}
					}

					return $active;
				}
			);
			$cache       = new \icl_cache(
				'language_name',
				true,
				WPML_LANGUAGE_DETAILS_CACHE_OPTION,
				array( $classifier, 'is_cold' ),
				WPML_LANGUAGE_NAMES_CACHE_OPTION_PREFIX,
				array( $classifier, 'get_shard_display_language' )
			);
			self::$cache = $cache;
		}

		return self::$cache;
	}

	public static function applyUrlCodeMap( $map ) {
		if ( ! is_array( $map ) ) {
			return $map;
		}
		foreach ( self::urlCodeMap() as $code => $displayCode ) {
			if ( isset( $map[ $code ] ) ) {
				$map[ $code ] = $displayCode;
			}
		}

		return $map;
	}

	public static function canonical( $code ) {
		$canonical = array_search( (string) $code, self::urlCodeMap(), true );

		return false === $canonical ? (string) $code : $canonical;
	}

	public static function urlCodeMap(): array {
		if ( ! WpmlPresence::onCurrentBlog() ) {
			return [];
		}

		$map = [];
		foreach ( self::rows() as $code => $row ) {
			$displayCode = \WPML\Core\Component\LanguageEditor\Domain\CustomLanguageFormat::usableDisplayCode(
				isset( $row['display_code'] ) ? $row['display_code'] : null
			);
			if ( '' !== $displayCode && $displayCode !== (string) $code ) {
				$map[ (string) $code ] = $displayCode;
			}
		}

		return $map;
	}

	public static function rows( $displayLanguage = null, bool $activeOnly = true, bool $refresh = false, $majorFirst = false, string $orderBy = 'english_name' ): array {
		if ( ! $displayLanguage ) {
			$displayLanguage = self::defaultLanguage();
		}

		if ( ! $refresh ) {
			$prefix = $activeOnly ? 'in_language_' : 'all_language_';
			$rows   = self::cache()->get( $prefix . $displayLanguage . '_' . $majorFirst . '_' . $orderBy );
			if ( $rows ) {
				return $rows;
			}
		}

		if ( function_exists( 'wpml_get_setup_instance' ) ) {
			$rows = wpml_get_setup_instance()->refresh_active_lang_cache( $displayLanguage, $activeOnly, $majorFirst, $orderBy );

			return is_array( $rows ) ? $rows : [];
		}

		return [];
	}

	public static function invalidate(): void {
		if ( self::$invalidating ) {
			return;
		}

		self::$invalidating = true;
		try {
			self::cache()->clear();
		} finally {
			self::$invalidating = false;
		}
	}

	public static function invalidateForLegacyClear(): void {
		if ( \icl_cache::is_language_name_preserved_during_clear() ) {
			return;
		}

		self::invalidate();
	}

	private static function defaultLanguage(): string {
		$settings = RequestSettings::load();

		return isset( $settings['default_language'] ) ? (string) $settings['default_language'] : 'en';
	}

	private static function ensureCacheApi(): void {
		if ( ! class_exists( 'icl_cache', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/inc/cache.php';
		}
	}
}
