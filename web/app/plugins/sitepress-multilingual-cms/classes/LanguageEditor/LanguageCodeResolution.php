<?php

namespace WPML\LanguageEditor;

use WPML\Core\Component\LanguageEditor\Domain\Bcp47;
use WPML\Core\Component\LanguageEditor\Domain\LanguageDerivation;
use WPML\Core\Component\LanguageEditor\Domain\LanguagePairKey;
use WPML\Upgrade\SchemaFacts;

final class LanguageCodeResolution {

	const PAIRS_TABLE     = 'icl_language_preset_countries';
	const PRESETS_TABLE   = 'icl_language_presets';
	const LANGUAGES_TABLE = 'icl_languages';

	private static $pairs = null;

	private static $primaryCodes = null;

	private static $presets = null;

	private static $storedTags = null;

	private static $pairsByPreset = null;

	private static $storedCountries = null;

	private static $customCodes = null;

	public static function resetCache() {
		self::$pairs           = null;
		self::$pairsByPreset   = null;
		self::$primaryCodes    = null;
		self::$presets         = null;
		self::$storedTags      = null;
		self::$customCodes     = null;
		self::$storedCountries = null;
		SchemaFacts::forget();
	}

	public static function resetStoredTags() {
		self::$storedTags      = null;
		self::$customCodes     = null;
		self::$storedCountries = null;
		SchemaFacts::forget();
	}

	public static function resolve( $code ) {
		$code = strtolower( trim( (string) $code ) );
		if ( '' === $code ) {
			return null;
		}

		self::load();
		self::loadStoredTags();

		if ( isset( self::$storedCountries[ $code ] ) ) {
			$country = self::$storedCountries[ $code ];
			$head    = self::presetHeadOf( $code );

			if ( null !== $head && isset( self::$pairsByPreset[ $head ][ $country ] ) ) {
				return self::presetIdentity(
					$head,
					$country,
					self::boundFlag( $head, $country, $code )
				);
			}
		}

		if ( isset( self::$pairs[ $code ] ) ) {
			$rows = self::$pairs[ $code ];

			$chosen = $rows[0];
			foreach ( $rows as $row ) {
				if ( isset( self::$primaryCodes[ $row['preset'] ] ) && self::$primaryCodes[ $row['preset'] ] === $code ) {
					$chosen = $row;
					break;
				}
			}

			return self::presetIdentity(
				$chosen['preset'],
				'' !== $chosen['country'] ? $chosen['country'] : null,
				self::firstFlagOfCode( $code )
			);
		}

		$preset = self::presetByCode( $code );
		if ( null !== $preset ) {
			return self::presetIdentity( $preset['code'], null, self::defaultFlagOfPreset( $preset['code'] ) );
		}

		return null;
	}

	private static function presetIdentity( $presetCode, $country, $flag ) {
		$preset = isset( self::$presets[ $presetCode ] ) ? self::$presets[ $presetCode ] : null;
		$script = null !== $preset ? $preset['script'] : null;

		$language = null !== $preset && '' !== $preset['language']
			? $preset['language']
			: LanguagePairKey::languageSubtag( (string) $presetCode, $script );

		return [
			'preset_code'  => (string) $presetCode,
			'language'     => $language,
			'script'       => $script,
			'country'      => $country,
			'english_name' => null !== $preset ? $preset['english_name'] : '',
			'wp_code'      => null !== $preset ? $preset['wp_code'] : null,
			'primary_code' => isset( self::$primaryCodes[ $presetCode ] ) ? self::$primaryCodes[ $presetCode ] : null,
			'pair_flag'    => $flag,
			'type'         => null !== $preset ? $preset['type'] : '',
			'rtl'          => self::presetRtl( $preset ),
		];
	}

	private static function presetRtl( $preset ) {
		return null !== $preset && array_key_exists( 'rtl', $preset ) && null !== $preset['rtl']
			? (bool) $preset['rtl']
			: null;
	}

	private static function presetHeadOf( $code ) {
		if ( isset( self::$pairs[ $code ] ) ) {
			$rows   = self::$pairs[ $code ];
			$chosen = $rows[0];
			foreach ( $rows as $row ) {
				if ( isset( self::$primaryCodes[ $row['preset'] ] ) && self::$primaryCodes[ $row['preset'] ] === $code ) {
					$chosen = $row;
					break;
				}
			}

			return $chosen['preset'];
		}

		$own = self::presetByCode( $code );
		if ( null !== $own ) {
			return $own['code'];
		}

		$confirmed = self::confirmedParseHead( $code );
		if ( null !== $confirmed ) {
			$preset = self::presetByCode( $confirmed );
			if ( null !== $preset ) {
				return $preset['code'];
			}
		}

		return self::solePresetOfLanguage( self::proposedLanguage( $code ) );
	}

	private static function proposedLanguage( $code ) {
		self::loadStoredTags();

		$key = strtolower( trim( (string) $code ) );

		if ( isset( self::$storedTags[ $key ] ) ) {
			return strtolower( trim( (string) Bcp47::languageSubtag( self::$storedTags[ $key ] ) ) );
		}

		$parts = explode( '-', $key );

		return (string) $parts[0];
	}

	private static function solePresetOfLanguage( $language ) {
		if ( '' === $language ) {
			return null;
		}

		$found = null;
		foreach ( self::$presets as $presetCode => $preset ) {
			if ( '' === $preset['language'] || strtolower( $preset['language'] ) !== $language ) {
				continue;
			}
			if ( null !== $found ) {
				return null;
			}
			$found = (string) $presetCode;
		}

		return $found;
	}

	private static function boundFlag( $presetCode, $country, $code ) {
		if ( ! empty( self::$pairsByPreset[ $presetCode ][ $country ]['flag'] ) ) {
			return self::$pairsByPreset[ $presetCode ][ $country ]['flag'];
		}

		$fromCode = self::firstFlagOfCode( $code );
		if ( null !== $fromCode ) {
			return $fromCode;
		}

		return self::firstFlagOfPreset( $presetCode );
	}

	private static function firstFlagOfCode( $code ) {
		if ( ! isset( self::$pairs[ $code ] ) ) {
			return null;
		}

		foreach ( self::$pairs[ $code ] as $row ) {
			if ( '' !== $row['flag'] ) {
				return $row['flag'];
			}
		}

		return null;
	}

	private static function defaultFlagOfPreset( $presetCode ) {
		if ( isset( self::$pairsByPreset[ $presetCode ] ) ) {
			foreach ( self::$pairsByPreset[ $presetCode ] as $pair ) {
				if ( $pair['is_default'] && '' !== $pair['flag'] ) {
					return $pair['flag'];
				}
			}
		}

		return self::firstFlagOfPreset( $presetCode );
	}

	private static function firstFlagOfPreset( $presetCode ) {
		if ( ! isset( self::$pairsByPreset[ $presetCode ] ) ) {
			return null;
		}

		foreach ( self::$pairsByPreset[ $presetCode ] as $pair ) {
			if ( '' !== $pair['flag'] ) {
				return $pair['flag'];
			}
		}

		return null;
	}

	public static function publishedIdentity( $code ) {
		if ( self::isCustomIdentity( $code ) ) {
			return [
				'language' => '',
				'script'   => null,
			];
		}

		$resolved = self::resolve( $code );
		if ( null !== $resolved ) {
			return [
				'language' => $resolved['language'],
				'script'   => $resolved['script'],
			];
		}

		$links = self::bridgedLinks( $code );
		if ( null !== $links ) {
			return [
				'language' => $links['language'],
				'script'   => $links['script'],
			];
		}

		return [
			'language' => '',
			'script'   => null,
		];
	}

	public static function isCustomIdentity( $code ) {
		self::loadStoredTags();

		$key = strtolower( trim( (string) $code ) );

		return isset( self::$customCodes[ $key ] ) && ! isset( self::$storedTags[ $key ] );
	}

	public static function isCustomOnCatalogueCode( $code ) {
		self::loadStoredTags();

		$key = strtolower( trim( (string) $code ) );
		if ( '' === $key || ! isset( self::$customCodes[ $key ] ) ) {
			return false;
		}

		self::load();

		return isset( self::$pairs[ $key ] ) || null !== self::presetByCode( $key );
	}

	public static function head( $code ) {
		$resolved = self::resolve( $code );
		if ( null !== $resolved ) {
			return LanguageDerivation::baseIdentifier( $resolved['language'], $resolved['script'] );
		}

		$confirmed = self::confirmedParseHead( $code );

		return null !== $confirmed ? $confirmed : (string) $code;
	}

	private static function confirmedParseHead( $code ) {
		$head = self::proposedHead( (string) $code );
		if ( '' === $head ) {
			return null;
		}

		self::load();

		if ( isset( self::$pairs[ $head ] ) || null !== self::presetByCode( $head ) ) {
			return $head;
		}

		foreach ( self::$presets as $preset ) {
			if ( $preset['language'] === $head ) {
				return $head;
			}
		}

		return null;
	}

	private static function proposedHead( $code ) {
		self::loadStoredTags();

		$key = strtolower( trim( (string) $code ) );
		if ( isset( self::$storedTags[ $key ] ) ) {
			$tag = self::$storedTags[ $key ];

			return LanguageDerivation::languageHead(
				LanguageDerivation::baseIdentifier(
					Bcp47::languageSubtag( $tag ),
					Bcp47::scriptSubtag( $tag )
				)
			);
		}

		return LanguageDerivation::languageHead( (string) $code );
	}

	private static function bridgedLinks( $code ) {
		$head = self::confirmedParseHead( $code );
		if ( null === $head ) {
			return null;
		}

		$resolved = self::resolve( $head );
		if ( null !== $resolved ) {
			return [
				'primary_code' => $resolved['primary_code'],
				'language'     => $resolved['language'],
				'script'       => $resolved['script'],
			];
		}

		$preset = self::presetByCode( $head );
		if ( null === $preset ) {
			return null;
		}

		$links = self::presetLinks( $preset['code'] );

		return [
			'primary_code' => $links['primary_code'],
			'language'     => $links['language'],
			'script'       => $preset['script'],
		];
	}

	public static function presetByCode( $code ) {
		$code = strtolower( trim( (string) $code ) );
		if ( '' === $code ) {
			return null;
		}

		self::load();

		foreach ( self::$presets as $presetCode => $preset ) {
			if ( strtolower( (string) $presetCode ) === $code ) {
				return array_merge( [ 'code' => (string) $presetCode ], $preset, [ 'rtl' => self::presetRtl( $preset ) ] );
			}
		}

		return null;
	}

	public static function ateLookupChain( $code ) {
		$code = (string) $code;
		$key  = strtolower( trim( $code ) );

		self::load();

		$primaries = [];
		$languages = [];

		if ( '' !== $key && isset( self::$pairs[ $key ] ) ) {
			$seen = [];
			foreach ( self::$pairs[ $key ] as $row ) {
				if ( isset( $seen[ $row['preset'] ] ) ) {
					continue;
				}
				$seen[ $row['preset'] ] = true;

				$links       = self::presetLinks( $row['preset'] );
				$primaries[] = $links['primary_code'];
				$languages[] = $links['language'];
			}
		} else {
			$links = self::bridgedLinks( $code );
			if ( null !== $links ) {
				$primaries[] = $links['primary_code'];
				$languages[] = $links['language'];
			}
		}

		$chain = [];
		foreach ( array_merge( [ $code ], $primaries, $languages ) as $candidate ) {
			$candidate = (string) $candidate;
			if ( '' !== $candidate && ! in_array( $candidate, $chain, true ) ) {
				$chain[] = $candidate;
			}
		}

		return $chain;
	}

	private static function presetLinks( $presetCode ) {
		$preset = isset( self::$presets[ $presetCode ] ) ? self::$presets[ $presetCode ] : null;
		$script = null !== $preset ? $preset['script'] : null;

		return [
			'primary_code' => isset( self::$primaryCodes[ $presetCode ] ) ? self::$primaryCodes[ $presetCode ] : null,
			'language'     => null !== $preset && '' !== $preset['language']
				? $preset['language']
				: LanguagePairKey::languageSubtag( (string) $presetCode, $script ),
		];
	}

	public static function country( $code ) {
		$resolved = self::resolve( $code );

		return null !== $resolved ? $resolved['country'] : null;
	}

	public static function isEnglish( $code ) {
		$resolved = self::resolve( $code );

		return null !== $resolved && 'en' === $resolved['language'];
	}

	private static function load() {
		if ( null !== self::$pairs ) {
			return;
		}

		global $wpdb;

		self::$pairs         = [];
		self::$pairsByPreset = [];
		self::$primaryCodes  = [];
		self::$presets       = [];

		if ( ! is_object( $wpdb ) ) {
			return;
		}

		self::loadPairs( $wpdb );
		self::loadPresets( $wpdb );
	}

	private static function loadStoredTags() {
		if ( null !== self::$storedTags ) {
			return;
		}

		global $wpdb;

		self::$storedTags      = [];
		self::$customCodes     = [];
		self::$storedCountries = [];

		if ( ! is_object( $wpdb ) ) {
			return;
		}

		$table = $wpdb->prefix . self::LANGUAGES_TABLE;

		if ( ! SchemaFacts::hasTable( self::LANGUAGES_TABLE ) ) {
			return;
		}

		if ( ! SchemaFacts::hasColumn( self::LANGUAGES_TABLE, 'bcp_47' ) ) {
			return;
		}

		$hasCustom = SchemaFacts::hasColumn( self::LANGUAGES_TABLE, 'is_custom' );

		$hasCountry = SchemaFacts::hasColumn( self::LANGUAGES_TABLE, 'country' );

		$columns = 'code, bcp_47' . ( $hasCustom ? ', is_custom' : '' ) . ( $hasCountry ? ', country' : '' );

		$rows = $wpdb->get_results( "SELECT {$columns} FROM `{$table}`" );
		SchemaFacts::forgetIfQueryFailed( $wpdb );

		foreach ( (array) $rows as $row ) {
			if ( ! isset( $row->code ) ) {
				continue;
			}

			$code = strtolower( trim( (string) $row->code ) );
			if ( '' === $code ) {
				continue;
			}

			$tag = isset( $row->bcp_47 ) ? trim( (string) $row->bcp_47 ) : '';
			if ( '' !== $tag ) {
				self::$storedTags[ $code ] = $tag;
			}

			if ( $hasCustom && ! empty( $row->is_custom ) ) {
				self::$customCodes[ $code ] = true;
			}

			if ( $hasCountry && isset( $row->country ) ) {
				$country = strtoupper( trim( (string) $row->country ) );
				if ( '' !== $country ) {
					self::$storedCountries[ $code ] = $country;
				}
			}
		}
	}

	private static function loadPairs( $wpdb ) {
		$table = $wpdb->prefix . self::PAIRS_TABLE;
		if ( ! SchemaFacts::hasTable( self::PAIRS_TABLE ) ) {
			return;
		}

		$rows = $wpdb->get_results( "SELECT code, preset_code, country_code, is_default, sort_order, flag FROM `{$table}` ORDER BY preset_code ASC, sort_order ASC" );

		$defaults = [];
		foreach ( (array) $rows as $row ) {
			$preset  = (string) $row->preset_code;
			$country = strtoupper( (string) $row->country_code );
			$code    = null !== $row->code ? strtolower( trim( (string) $row->code ) ) : '';

			if ( '' === $code ) {
				continue;
			}

			$flag = null !== $row->flag ? (string) $row->flag : '';

			self::$pairs[ $code ][] = [
				'preset'  => $preset,
				'country' => $country,
				'flag'    => $flag,
			];

			if ( ! isset( self::$pairsByPreset[ $preset ][ $country ] ) ) {
				self::$pairsByPreset[ $preset ][ $country ] = [
					'code'       => $code,
					'flag'       => $flag,
					'is_default' => 1 === (int) $row->is_default,
				];
			}

			if ( '' === $country ) {
				self::$primaryCodes[ $preset ] = $code;
			} elseif ( 1 === (int) $row->is_default ) {
				$defaults[ $preset ] = $code;
			}
		}

		foreach ( $defaults as $preset => $code ) {
			if ( ! isset( self::$primaryCodes[ $preset ] ) ) {
				self::$primaryCodes[ $preset ] = $code;
			}
		}
	}

	private static function loadPresets( $wpdb ) {
		$table = $wpdb->prefix . self::PRESETS_TABLE;
		if ( ! SchemaFacts::hasTable( self::PRESETS_TABLE ) ) {
			return;
		}

		$hasLanguage = SchemaFacts::hasColumn( self::PRESETS_TABLE, 'language' );

		$hasRtl = SchemaFacts::hasColumn( self::PRESETS_TABLE, 'rtl' );

		$columns = 'code, english_name, script, type, wp_code' . ( $hasLanguage ? ', language' : '' ) . ( $hasRtl ? ', rtl' : '' );
		$rows = $wpdb->get_results( "SELECT {$columns} FROM `{$table}`" );
		SchemaFacts::forgetIfQueryFailed( $wpdb );

		foreach ( (array) $rows as $row ) {
			$script = null !== $row->script && '' !== (string) $row->script ? strtolower( (string) $row->script ) : null;

			self::$presets[ (string) $row->code ] = [
				'english_name' => (string) $row->english_name,
				'script'       => $script,
				'type'         => (string) $row->type,
				'wp_code'      => null !== $row->wp_code && '' !== (string) $row->wp_code ? (string) $row->wp_code : null,
				'language'     => $hasLanguage && null !== $row->language ? strtolower( trim( (string) $row->language ) ) : '',
				'rtl'          => $hasRtl && isset( $row->rtl ) ? (bool) (int) $row->rtl : null,
			];
		}
	}
}
