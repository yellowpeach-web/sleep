<?php

namespace WPML\LanguageEditor;

use WPML\Core\Component\LanguageEditor\Domain\EnglishNameQualification;
use WPML\LanguageEditor\Adapter\LanguageRepository;
use WPML\LanguageEditor\Presets\CountriesTranslationsData;
use WPML\LanguageEditor\Presets\LanguagesTranslationsData;

class LanguageNames {

	const TABLE = 'icl_languages_translations';

	const CODE_CAP = 7;

	private static $blocks = null;

	private static $pairs = null;

	private static $englishCountries = null;

	private static $englishLanguages = null;

	private static $activeEnglishLanguages = null;

	private static $countryNames = [];

	private static $englishNames = [];

	private static $storedEnglish = null;

	private static $pairOverrides = [];

	public static function resetCache() {
		self::$pairs                  = null;
		self::$englishCountries       = null;
		self::$englishLanguages       = null;
		self::$activeEnglishLanguages = null;
		self::$countryNames           = [];
		self::$englishNames           = [];
		self::$storedEnglish          = null;
	}

	public static function baseDisplayCode( $displayCode ) {
		$displayCode = (string) $displayCode;
		$blocks      = self::blocks();

		if ( isset( $blocks[ $displayCode ] ) ) {
			return $displayCode;
		}

		foreach ( self::pairsFor( $displayCode ) as $pair ) {
			if ( '' !== $pair['preset'] && isset( $blocks[ $pair['preset'] ] ) ) {
				return $pair['preset'];
			}
		}

		return '';
	}

	public static function nameFor( $code, $displayCode, $qualify = null ) {
		$code = (string) $code;
		$base = self::baseDisplayCode( $displayCode );
		if ( '' === $code || '' === $base ) {
			return null;
		}

		$blocks = self::blocks();
		$block  = $blocks[ $base ];

		if ( ! isset( self::$pairOverrides[ $code ] ) && isset( $blocks['en'][ $code ] ) ) {
			return isset( $block[ $code ] ) && '' !== $block[ $code ] ? (string) $block[ $code ] : null;
		}

		$pair = self::faithfulPair( $code );
		if ( null === $pair || ! isset( $block[ $pair['preset'] ] ) || '' === $block[ $pair['preset'] ] ) {
			return null;
		}

		$language = (string) $block[ $pair['preset'] ];
		if ( '' === $pair['country'] ) {
			return $language;
		}

		if ( null === $qualify ) {
			$qualify = self::isQualified( $code );
		}
		if ( ! $qualify ) {
			return $language;
		}

		$countries   = self::countryNames( $base );
		$countryName = isset( $countries[ $pair['country'] ] ) ? $countries[ $pair['country'] ] : '';
		if ( '' === $countryName ) {
			return null;
		}

		// Translators: %1$s language name, %2$s country name, e.g. "Spanish (Mexico)".
		return sprintf( '%1$s (%2$s)', $language, $countryName );
	}

	public static function shouldQualify( $code ) {
		$code = (string) $code;
		$pair = self::faithfulPair( $code );

		if ( null === $pair || '' === $pair['country'] ) {
			return false;
		}

		return in_array( $code, self::ambiguousCodes( self::displayedPresets() ), true );
	}

	public static function ambiguousCodes( array $presetByCode ) {
		$counts = [];
		foreach ( $presetByCode as $preset ) {
			$preset = (string) $preset;
			if ( '' === $preset ) {
				continue;
			}
			$counts[ $preset ] = isset( $counts[ $preset ] ) ? $counts[ $preset ] + 1 : 1;
		}

		$ambiguous = [];
		foreach ( $presetByCode as $code => $preset ) {
			$preset = (string) $preset;
			if ( '' !== $preset && isset( $counts[ $preset ] ) && $counts[ $preset ] > 1 ) {
				$ambiguous[] = (string) $code;
			}
		}

		return $ambiguous;
	}

	private static function displayedPresets() {
		$presets = self::activePresets();

		foreach ( self::removedWithContentCodes() as $code ) {
			if ( isset( $presets[ $code ] ) ) {
				continue;
			}
			$pair             = self::faithfulPair( $code );
			$presets[ $code ] = ( null !== $pair && '' !== $pair['preset'] ) ? $pair['preset'] : $code;
		}

		return $presets;
	}

	private static function removedWithContentCodes() {
		if ( ! class_exists( \WPML\Languages\RemovedLanguages::class ) ) {
			return [];
		}

		return array_map( 'strval', array_keys( \WPML\Languages\RemovedLanguages::withContent() ) );
	}

	private static function activePresets() {
		global $wpdb;

		$codes = (array) $wpdb->get_col( "SELECT code FROM {$wpdb->prefix}icl_languages WHERE active = 1" );

		$out = [];
		foreach ( $codes as $code ) {
			$code = (string) $code;
			if ( '' === $code ) {
				continue;
			}
			$pair         = self::faithfulPair( $code );
			$out[ $code ] = ( null !== $pair && '' !== $pair['preset'] ) ? $pair['preset'] : $code;
		}

		return $out;
	}

	public static function isQualified( $code ) {
		$code = (string) $code;
		$pair = self::faithfulPair( $code );
		if ( null === $pair || '' === $pair['country'] ) {
			return false;
		}

		$reference = self::blocks()['en'];
		$bare      = isset( $reference[ $pair['preset'] ] ) ? (string) $reference[ $pair['preset'] ] : '';
		$stored    = self::storedEnglishName( $code );

		if ( '' === $bare || '' === $stored ) {
			return true;
		}

		return $stored !== $bare;
	}

	private static function storedEnglishName( $code ) {
		global $wpdb;

		$code = (string) $code;

		if ( null === self::$storedEnglish ) {
			self::$storedEnglish = [];

			$table = $wpdb->prefix . self::TABLE;
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				$rows = $wpdb->get_results( "SELECT language_code, name FROM `{$table}` WHERE display_language_code = 'en'" );
				foreach ( (array) $rows as $row ) {
					self::$storedEnglish[ (string) $row->language_code ] = (string) $row->name;
				}
			}
		}

		if ( isset( self::$storedEnglish[ $code ] ) && '' !== self::$storedEnglish[ $code ] ) {
			return self::$storedEnglish[ $code ];
		}

		$legacy = self::englishLanguages();

		return isset( $legacy[ $code ] ) ? $legacy[ $code ] : '';
	}

	private static function freezeEnglishName( $code, $qualify, array $replaceable = [] ) {
		global $wpdb;

		$name = self::nameFor( $code, 'en', $qualify );
		if ( null === $name || '' === $name ) {
			$english = self::englishLanguages();
			$name    = isset( $english[ $code ] ) ? (string) $english[ $code ] : '';
		}
		if ( '' === $name ) {
			return;
		}

		$rowNames = self::displayedEnglishLanguages();
		$own      = isset( $rowNames[ $code ] ) ? (string) $rowNames[ $code ] : '';
		if ( '' !== $own && $own !== $name ) {
			foreach ( $rowNames as $otherCode => $otherName ) {
				if ( (string) $otherCode !== $code && (string) $otherName === $name ) {
					$name = $own;
					break;
				}
			}
		}

		$table = $wpdb->prefix . self::TABLE;

		$stored = self::storedEnglishName( $code );
		$hasRow = isset( self::$storedEnglish[ $code ] ) && '' !== self::$storedEnglish[ $code ];

		if ( ! $hasRow ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO `{$table}` (language_code, display_language_code, name) VALUES (%s, 'en', %s)",
					$code,
					$name
				)
			);
		} elseif ( $stored !== $name && in_array( $stored, array_merge( self::englishNames( $code ), $replaceable ), true ) ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$table}` SET name = %s
					 WHERE language_code = %s AND display_language_code = 'en' AND name = %s",
					$name,
					$code,
					$stored
				)
			);
		}

		self::$storedEnglish[ $code ] = $name;
	}

	private static function faithfulPair( $code ) {
		if ( isset( self::$pairOverrides[ (string) $code ] ) ) {
			return self::$pairOverrides[ (string) $code ];
		}

		$english   = self::englishLanguages();
		$reference = self::blocks()['en'];
		$name      = isset( $english[ $code ] ) ? $english[ $code ] : '';
		if ( '' === $name ) {
			return null;
		}

		$countries = self::englishCountries();

		foreach ( self::pairsFor( $code ) as $pair ) {
			$preset = $pair['preset'];
			if ( '' === $preset || ! isset( $reference[ $preset ] ) ) {
				continue;
			}

			$language = (string) $reference[ $preset ];
			if ( $language === $name ) {
				return [ 'preset' => $preset, 'country' => '' ];
			}

			$country = $pair['country'];
			if ( '' !== $country && isset( $countries[ $country ] ) ) {
				if ( sprintf( '%1$s (%2$s)', $language, $countries[ $country ] ) === $name ) {
					return [ 'preset' => $preset, 'country' => $country ];
				}
			}
		}

		return null;
	}

	public static function englishNames( $code ) {
		$code = (string) $code;
		if ( isset( self::$englishNames[ $code ] ) ) {
			return self::$englishNames[ $code ];
		}

		$bare       = self::nameFor( $code, 'en', false );
		$qualified  = self::nameFor( $code, 'en', true );
		$legacy     = self::englishLanguages();
		$builtIn = function_exists( 'icl_get_languages_codes' )
			? array_search( $code, icl_get_languages_codes(), true )
			: false;
		$candidates = [
			null !== $bare ? $bare : '',
			null !== $qualified ? $qualified : '',
			isset( $legacy[ $code ] ) ? $legacy[ $code ] : '',
			false !== $builtIn ? (string) $builtIn : '',
		];

		self::$englishNames[ $code ] = array_values( array_unique( array_filter( $candidates, 'strlen' ) ) );

		return self::$englishNames[ $code ];
	}

	public static function seed( array $languageCodes, array $displayCodes, array $qualify = [], array $replaceable = [] ) {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return false;
		}

		$languageCodes = array_values( array_unique( array_filter( array_map( 'strval', $languageCodes ), [ self::class, 'fitsCodeCap' ] ) ) );
		$displayCodes  = array_values( array_unique( array_filter( array_map( 'strval', $displayCodes ), [ self::class, 'fitsCodeCap' ] ) ) );
		if ( empty( $languageCodes ) || empty( $displayCodes ) ) {
			return true;
		}

		$existing = self::storedNames( $table );
		$english  = self::englishLanguages();

		$inserts = [];
		$updates = [];
		foreach ( $displayCodes as $display ) {
			$base = self::baseDisplayCode( $display );

			if ( 'en' === $display ) {
				continue;
			}

			foreach ( $languageCodes as $code ) {
				$name = '' === $base ? null : self::nameFor(
					$code,
					$display,
					array_key_exists( $code, $qualify ) ? (bool) $qualify[ $code ] : null
				);

				if ( null === $name && '' !== $base && $display !== $base && isset( $existing[ $code ][ $base ] ) ) {
					$inherited = $existing[ $code ][ $base ];
					if ( '' !== $inherited && ! in_array( $inherited, self::englishNames( $code ), true ) ) {
						$name = $inherited;
					}
				}

				if ( ( null === $name || '' === $name )
					&& isset( $english[ $code ] ) && '' !== $english[ $code ]
					&& ( ! isset( $existing[ $code ][ $display ] )
						|| ( isset( $replaceable[ $code ] )
							&& in_array( $existing[ $code ][ $display ], (array) $replaceable[ $code ], true ) ) ) ) {
					$name = $english[ $code ];
				}

				if ( null === $name || '' === $name ) {
					continue;
				}

				if ( ! isset( $existing[ $code ][ $display ] ) ) {
					$inserts[] = [ $code, $display, $name ];
					continue;
				}

				$stored   = $existing[ $code ][ $display ];
				$machine  = self::englishNames( $code );
				if ( isset( $replaceable[ $code ] ) ) {
					$machine = array_merge( $machine, (array) $replaceable[ $code ] );
				}
				if ( $stored !== $name && in_array( $stored, $machine, true ) ) {
					$updates[] = [ $code, $display, $name, $stored ];
				}
			}
		}

		return self::write( $table, $inserts, $updates );
	}

	public static function seedNewLanguage( $code ) {
		global $wpdb;

		$code = (string) $code;
		if ( '' === $code ) {
			return;
		}

		self::resetCache();

		$table = $wpdb->prefix . self::TABLE;
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return;
		}

		$qualify = [ $code => self::shouldQualify( $code ) ];

		self::freezeEnglishName( $code, $qualify[ $code ] );

		$displays  = (array) $wpdb->get_col( "SELECT code FROM {$wpdb->prefix}icl_languages WHERE active = 1" );
		$languages = (array) $wpdb->get_col( "SELECT DISTINCT language_code FROM `{$table}`" );

		self::seed( [ $code ], array_merge( $displays, [ $code ] ), $qualify );
		self::seed( array_merge( $languages, [ $code ] ), [ $code ], $qualify );
	}

	public static function reseedForIdentityChange( $code, $presetCode = '', $previousCountry = '' ) {
		global $wpdb;

		$code = (string) $code;
		if ( '' === $code ) {
			return;
		}

		self::resetCache();

		$table = $wpdb->prefix . self::TABLE;
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return;
		}

		$resolved = LanguageCodeResolution::resolve( $code );
		if ( null === $resolved || '' === (string) $resolved['preset_code'] ) {
			return;
		}

		$stale = array_values( array_unique( array_merge( self::machineNames( $code ), self::englishNames( $code ) ) ) );

		$country = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT country FROM {$wpdb->prefix}icl_languages WHERE code = %s LIMIT 1",
				$code
			)
		);

		$previousCountry = strtoupper( trim( (string) $previousCountry ) );
		if ( '' === $presetCode && '' !== $previousCountry && $previousCountry !== strtoupper( trim( $country ) ) ) {
			$hadOverride                  = isset( self::$pairOverrides[ $code ] ) ? self::$pairOverrides[ $code ] : null;
			self::$pairOverrides[ $code ] = [ 'preset' => (string) $resolved['preset_code'], 'country' => $previousCountry ];
			self::$englishNames           = [];
			self::$countryNames           = [];
			self::$storedEnglish          = null;
			$stale                        = array_values( array_unique( array_merge( $stale, self::machineNames( $code ) ) ) );
			if ( null === $hadOverride ) {
				unset( self::$pairOverrides[ $code ] );
			} else {
				self::$pairOverrides[ $code ] = $hadOverride;
			}
			self::$englishNames  = [];
			self::$countryNames  = [];
			self::$storedEnglish = null;
		}

		$target = LanguageCodeResolution::presetByCode( $presetCode );

		self::$pairOverrides[ $code ] = [
			'preset'  => null !== $target ? (string) $target['code'] : (string) $resolved['preset_code'],
			'country' => strtoupper( trim( $country ) ),
		];

		try {
			self::$englishNames  = [];
			self::$countryNames  = [];
			self::$storedEnglish = null;

			$qualify = [ $code => self::shouldQualify( $code ) ];

			self::renameRowEnglishName( $code, $qualify[ $code ] );

			self::freezeEnglishName( $code, $qualify[ $code ], $stale );

			$displays  = (array) $wpdb->get_col( "SELECT code FROM {$wpdb->prefix}icl_languages WHERE active = 1" );
			$languages = (array) $wpdb->get_col( "SELECT DISTINCT language_code FROM `{$table}`" );

			self::seed( [ $code ], array_merge( $displays, [ $code ] ), $qualify, [ $code => $stale ] );
			self::seed( array_merge( $languages, [ $code ] ), [ $code ], $qualify );
		} finally {
			unset( self::$pairOverrides[ $code ] );
			self::resetCache();
		}
	}

	private static function renameRowEnglishName( $code, $qualify ) {
		global $wpdb;

		$rowNames = self::englishLanguages();
		$own      = isset( $rowNames[ $code ] ) ? (string) $rowNames[ $code ] : '';
		$bare     = (string) self::nameFor( $code, 'en', false );

		$candidates = array_values(
			array_unique(
				array_filter(
					[ (string) self::nameFor( $code, 'en', $qualify ), (string) self::nameFor( $code, 'en', true ) ],
					'strlen'
				)
			)
		);

		$name = '';
		foreach ( $candidates as $candidate ) {
			if ( self::claimEnglishName( $candidate, $code, $candidate === $bare ) ) {
				$name = $candidate;
				break;
			}
		}

		if ( '' === $name || $name === $own ) {
			return;
		}

		$wpdb->update(
			$wpdb->prefix . 'icl_languages',
			[ 'english_name' => $name ],
			[ 'code' => $code ]
		);

		self::$englishLanguages       = null;
		self::$activeEnglishLanguages = null;
		self::$englishNames           = [];
	}

	private static function claimEnglishName( $name, $code, $plain ) {
		$languages = new LanguageRepository();
		$owner     = $languages->englishNameOwner( $name, $code );

		if ( null === $owner ) {
			return true;
		}

		if ( ! empty( $owner['active'] ) || $languages->hasRetainedContent( (string) $owner['code'] ) ) {
			return false;
		}

		$languages->renameEnglishName(
			(int) $owner['id'],
			$plain
				? EnglishNameQualification::qualifiedName( $name, $owner, $languages )
				: EnglishNameQualification::yieldedName( $name, $owner, $languages )
		);

		return true;
	}

	private static function machineNames( $code ) {
		global $wpdb;

		$displays = (array) $wpdb->get_col( "SELECT code FROM {$wpdb->prefix}icl_languages WHERE active = 1" );

		$names = [];
		foreach ( array_merge( $displays, [ 'en', (string) $code ] ) as $display ) {
			foreach ( [ true, false ] as $qualify ) {
				$name = self::nameFor( $code, $display, $qualify );
				if ( null !== $name && '' !== $name ) {
					$names[] = $name;
				}
			}
		}

		return array_values( array_unique( $names ) );
	}

	private static function write( $table, array $inserts, array $updates ) {
		global $wpdb;

		$ok = true;

		foreach ( array_chunk( $inserts, 100 ) as $chunk ) {
			$rows = [];
			$args = [];
			foreach ( $chunk as $row ) {
				$rows[] = '(%s, %s, %s)';
				$args[] = (string) $row[0];
				$args[] = (string) $row[1];
				$args[] = (string) $row[2];
			}

			$done = $wpdb->query(
				$wpdb->prepare(
					'INSERT IGNORE INTO `' . esc_sql( $table ) . '` (language_code, display_language_code, name) VALUES '
					. implode( ', ', array_fill( 0, count( $rows ), '(%s, %s, %s)' ) ),
					$args[0],
					$args[1],
					...array_slice( $args, 2 )
				)
			);
			$ok = $ok && ( false !== $done );
		}

		foreach ( array_chunk( $updates, 100 ) as $chunk ) {
			$cases = [];
			$where = [];
			$args  = [];
			foreach ( $chunk as $row ) {
				$cases[] = 'WHEN language_code = %s AND display_language_code = %s AND name = %s THEN %s';
				array_push( $args, $row[0], $row[1], $row[3], $row[2] );
			}
			foreach ( $chunk as $row ) {
				$where[] = '(language_code = %s AND display_language_code = %s)';
				array_push( $args, $row[0], $row[1] );
			}

			$done = $wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$table}` SET name = CASE " . implode( ' ', $cases ) . ' ELSE name END
					 WHERE ' . implode( ' OR ', $where ),
					$args
				)
			);
			$ok = $ok && ( false !== $done );
		}

		return $ok;
	}

	private static function storedNames( $table ) {
		global $wpdb;

		$rows = $wpdb->get_results( "SELECT language_code, display_language_code, name FROM `{$table}`", ARRAY_A );

		$out = [];
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['language_code'] ][ (string) $row['display_language_code'] ] = (string) $row['name'];
		}

		return $out;
	}

	private static function blocks() {
		if ( null === self::$blocks ) {
			self::$blocks = LanguagesTranslationsData::data();
		}

		return self::$blocks;
	}

	public static function overlayBlocks( array $blocks ) {
		$merged = self::blocks();

		foreach ( $blocks as $display => $names ) {
			$display = strtolower( (string) $display );
			if ( ! self::fitsCodeCap( $display ) || ! is_array( $names ) ) {
				continue;
			}
			foreach ( $names as $code => $name ) {
				$code = strtolower( (string) $code );
				$name = trim( (string) $name );
				if ( ! self::fitsCodeCap( $code ) || '' === $name ) {
					continue;
				}
				$merged[ $display ][ $code ] = $name;
			}
		}

		self::$blocks = $merged;
		self::resetCache();
	}

	public static function fitsCodeCap( $code ) {
		$code = (string) $code;

		return '' !== $code && strlen( $code ) <= self::CODE_CAP;
	}

	private static function pairsFor( $code ) {
		if ( null === self::$pairs ) {
			global $wpdb;

			self::$pairs = [];
			$table       = $wpdb->prefix . 'icl_language_preset_countries';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				$rows = $wpdb->get_results( "SELECT code, preset_code, country_code FROM `{$table}`" );
				foreach ( (array) $rows as $row ) {
					self::$pairs[ (string) $row->code ][] = [
						'preset'  => (string) $row->preset_code,
						'country' => strtoupper( (string) $row->country_code ),
					];
				}
			}
		}

		$code = (string) $code;

		return isset( self::$pairs[ $code ] ) ? self::$pairs[ $code ] : [];
	}

	private static function countryNames( $blockCode ) {
		$blockCode = (string) $blockCode;
		if ( isset( self::$countryNames[ $blockCode ] ) ) {
			return self::$countryNames[ $blockCode ];
		}

		$catalogue = CountriesTranslationsData::data();
		$localized = isset( $catalogue[ $blockCode ] ) ? $catalogue[ $blockCode ] : [];

		$names = self::englishCountries();
		foreach ( $localized as $country => $name ) {
			if ( '' !== trim( (string) $name ) ) {
				$names[ strtoupper( (string) $country ) ] = (string) $name;
			}
		}

		self::$countryNames[ $blockCode ] = $names;

		return $names;
	}

	private static function englishCountries() {
		if ( null !== self::$englishCountries ) {
			return self::$englishCountries;
		}

		global $wpdb;

		self::$englishCountries = [];
		$table                  = $wpdb->prefix . 'icl_countries';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			$rows = $wpdb->get_results( "SELECT code, english_name FROM `{$table}`" );
			foreach ( (array) $rows as $row ) {
				self::$englishCountries[ strtoupper( (string) $row->code ) ] = (string) $row->english_name;
			}
		}

		return self::$englishCountries;
	}

	private static function englishLanguages() {
		if ( null !== self::$englishLanguages ) {
			return self::$englishLanguages;
		}

		global $wpdb;

		self::$englishLanguages = [];
		$rows = $wpdb->get_results( "SELECT code, english_name FROM {$wpdb->prefix}icl_languages" );
		foreach ( (array) $rows as $row ) {
			self::$englishLanguages[ (string) $row->code ] = (string) $row->english_name;
		}

		return self::$englishLanguages;
	}

	private static function displayedEnglishLanguages() {
		$names = self::activeEnglishLanguages();

		if ( ! self::removedWithContentCodes() ) {
			return $names;
		}

		$stored = self::englishLanguages();
		foreach ( self::removedWithContentCodes() as $code ) {
			if ( ! isset( $names[ $code ] ) && isset( $stored[ $code ] ) ) {
				$names[ $code ] = (string) $stored[ $code ];
			}
		}

		return $names;
	}

	private static function activeEnglishLanguages() {
		if ( null !== self::$activeEnglishLanguages ) {
			return self::$activeEnglishLanguages;
		}

		global $wpdb;

		self::$activeEnglishLanguages = [];
		$rows = $wpdb->get_results( "SELECT code, english_name FROM {$wpdb->prefix}icl_languages WHERE active = 1" );
		foreach ( (array) $rows as $row ) {
			self::$activeEnglishLanguages[ (string) $row->code ] = (string) $row->english_name;
		}

		return self::$activeEnglishLanguages;
	}
}
