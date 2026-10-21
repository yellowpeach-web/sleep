<?php

namespace WPML\LanguageEditor\Presets;

use WPML\Core\Component\LanguageEditor\Domain\LanguagePairKey;
use WPML\LanguageEditor\Flags\FlagManifest;
use WPML\LanguageEditor\LanguageCodeResolution;
use WPML\Upgrade\Commands\CreateLanguagePresetsTable;

class PresetsSeeder {

	private $wpdb;

	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public static function create() {
		global $wpdb;

		return new self( $wpdb );
	}

	public function table() {
		return $this->wpdb->prefix . CreateLanguagePresetsTable::TABLE_NAME;
	}

	public static function data() {
		return LanguagePresetsData::data();
	}

	public function seed() {
		$wpdb  = $this->wpdb;
		$table = $this->table();

		$exists = (string) $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);
		if ( 0 !== strcasecmp( $exists, $table ) ) {
			return -1;
		}

		$rows = self::data();
		if ( ! $rows ) {
			return -1;
		}

		return $this->upsert( $rows );
	}

	public function upsert( array $rows ) {
		$written = 0;
		$table   = $this->table();

		$hasLangFlag = $this->columnExists( 'language_flag' );
		$hasWpCode   = $this->columnExists( 'wp_code' );
		$hasTier     = $this->columnExists( 'visibility_tier' );
		$hasLanguage = $this->columnExists( 'language' );

		$m2m = [];

		foreach ( array_chunk( $rows, 100 ) as $chunk ) {
			$values       = [];
			$placeholders = [];

			$columns = [ 'code', 'english_name', 'script', 'type', 'country_mode' ];
			if ( $hasLanguage ) {
				$columns[] = 'language';
			}
			if ( $hasLangFlag ) {
				$columns[] = 'language_flag';
			}
			if ( $hasWpCode ) {
				$columns[] = 'wp_code';
			}
			$columns[] = 'major';
			if ( $hasTier ) {
				$columns[] = 'visibility_tier';
			}

			foreach ( $chunk as $row ) {
				$row = self::normalize( $row );
				if ( null === $row ) {
					continue;
				}

				$rowPlaceholders = [ '%s', '%s', '%s', '%s', '%s' ];
				$rowValues       = [
					$row['code'],
					$row['english_name'],
					$row['script'],
					$row['type'],
					$row['country_mode'],
				];
				if ( $hasLanguage ) {
					$rowPlaceholders[] = '%s';
					$rowValues[]       = $row['language'];
				}
				if ( $hasLangFlag ) {
					$rowPlaceholders[] = '%s';
					$rowValues[]       = $this->languageFlag( $row['code'], $row['script'], $row['language'] );
				}
				if ( $hasWpCode ) {
					$rowPlaceholders[] = '%s';
					$rowValues[]       = $row['wp_code'];
				}
				$rowPlaceholders[] = '%d';
				$rowValues[]       = $row['major'];
				if ( $hasTier ) {
					$rowPlaceholders[] = '%s';
					$rowValues[]       = $row['visibility_tier'];
				}

				$placeholders[] = '(' . implode( ', ', $rowPlaceholders ) . ')';
				array_push( $values, ...$rowValues );

				$m2m[ (string) $row['code'] ] = [
					'allowed'      => array_map( 'strval', (array) $row['allowed'] ),
					'default'      => null !== $row['default_country'] ? (string) $row['default_country'] : null,
					'script'       => $row['script'],
					'language'     => $row['language'],
					'type'         => $row['type'],
					'country_mode' => $row['country_mode'],
					'language_flag' => $this->languageFlag( $row['code'], $row['script'], $row['language'] ),
					'locale_by_country' => $row['locale_by_country'],
					'wp_code'      => $row['wp_code'],
					'pair_codes'   => $row['pair_codes'],
				];
			}

			if ( ! $placeholders ) {
				continue;
			}

			$column_list = '`' . implode( '`, `', $columns ) . '`';
			$updates     = [];
			foreach ( $columns as $col ) {
				if ( 'code' === $col ) {
					continue;
				}
				$updates[] = 'language_flag' === $col
					? FlagUpsertRule::fillWhenEmpty( $col )
					: "`{$col}` = VALUES(`{$col}`)";
			}

			$sql = "INSERT INTO `{$table}` ({$column_list})
					VALUES " . implode( ', ', $placeholders ) . '
					ON DUPLICATE KEY UPDATE ' . implode( ', ', $updates );

			$result = $this->wpdb->query( $this->wpdb->prepare( $sql, $values ) );

			if ( false !== $result ) {
				$written += min( (int) $result, count( $placeholders ) );
			}
		}

		$this->seedPresetCountries( $m2m );

		LanguageCodeResolution::resetCache();

		return $written;
	}

	private function seedPresetCountries( array $m2m ) {
		$table = $this->wpdb->prefix . \WPML\Upgrade\Commands\CreateLanguagePresetCountriesTable::TABLE_NAME;
		$exists = (string) $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( 0 !== strcasecmp( $exists, $table ) ) {
			return;
		}

		$hasPrecomputed = $this->columnExists2( 'code' );
		$pairs          = $hasPrecomputed ? $this->computePairData( $m2m ) : [];

		$hasPickerDefault = $this->columnExists2( 'picker_default' );
		$pickerDefaults   = $hasPickerDefault ? self::pickerDefaultKeys() : [];

		$batch = [];
		foreach ( $m2m as $code => $data ) {
			$default = null !== $data['default'] ? strtoupper( (string) $data['default'] ) : null;
			$rows = [];
			if ( isset( $pairs[ $code ][''] ) ) {
				$rows[''] = [ 'is_default' => 0, 'sort_order' => -1 ];
			}
			$order = 0;
			$seen  = [];
			foreach ( (array) $data['allowed'] as $country ) {
				$cc = strtoupper( trim( (string) $country ) );
				if ( '' === $cc || isset( $seen[ $cc ] ) ) {
					continue;
				}
				$seen[ $cc ] = true;
				$rows[ $cc ] = [ 'is_default' => ( null !== $default && $default === $cc ) ? 1 : 0, 'sort_order' => $order ];
				++$order;
			}
			if ( null !== $default && ! isset( $seen[ $default ] ) ) {
				$rows[ $default ] = [ 'is_default' => 1, 'sort_order' => $order ];
			}

			foreach ( $rows as $cc => $meta ) {
				if ( $hasPrecomputed && '' !== $cc && ! isset( $pairs[ $code ][ $cc ] ) ) {
					continue;
				}
				$row = [
					'preset_code'  => $code,
					'country_code' => $cc,
					'is_default'   => $meta['is_default'],
					'sort_order'   => $meta['sort_order'],
				];
				if ( $hasPrecomputed && isset( $pairs[ $code ][ $cc ] ) ) {
					$pair                  = $pairs[ $code ][ $cc ];
					$row['code']           = $pair['code'];
					$row['default_locale'] = $pair['default_locale'];
					$row['flag']           = $pair['flag'];
				}
				if ( $hasPickerDefault ) {
					$row['picker_default'] = isset( $pickerDefaults[ $code . '|' . $cc ] ) ? 1 : 0;
				}
				$batch[] = $row;
			}
		}

		$codes = array_keys( $m2m );
		if ( $codes ) {
			$in = implode( ', ', array_fill( 0, count( $codes ), '%s' ) );
			$this->wpdb->query( $this->wpdb->prepare( "UPDATE `{$table}` SET `is_default` = 0 WHERE `preset_code` IN ({$in})", $codes ) );
		}

		foreach ( array_chunk( $batch, 500 ) as $chunk ) {
			if ( ! $this->upsertPresetCountries( $table, $chunk, $hasPrecomputed, $hasPickerDefault ) ) {
				return;
			}
		}
	}

	private function upsertPresetCountries( $table, array $rows, $hasPrecomputed, $hasPickerDefault = false ) {
		if ( ! $rows ) {
			return true;
		}

		$columns = [ 'preset_code', 'country_code', 'is_default', 'sort_order' ];
		$formats = [ '%s', '%s', '%d', '%d' ];
		if ( $hasPrecomputed ) {
			array_push( $columns, 'code', 'default_locale', 'flag' );
			array_push( $formats, '%s', '%s', '%s' );
		}
		if ( $hasPickerDefault ) {
			$columns[] = 'picker_default';
			$formats[] = '%d';
		}

		$placeholders = [];
		$args         = [];
		foreach ( $rows as $row ) {
			$rowFormats = $formats;
			$rowValues  = [];
			foreach ( $columns as $i => $col ) {
				if ( ! array_key_exists( $col, $row ) ) {
					$rowFormats[ $i ] = 'NULL';
					continue;
				}
				$rowValues[] = $row[ $col ];
			}
			$placeholders[] = '(' . implode( ', ', $rowFormats ) . ')';
			array_push( $args, ...$rowValues );
		}

		$column_list = '`' . implode( '`, `', $columns ) . '`';
		$updates     = [];
		foreach ( $columns as $col ) {
			if ( 'preset_code' === $col || 'country_code' === $col ) {
				continue;
			}
			if ( 'picker_default' === $col ) {
				continue;
			}
			$updates[] = 'flag' === $col
				? FlagUpsertRule::fillWhenEmpty( $col )
				: "`{$col}` = VALUES(`{$col}`)";
		}

		$sql = "INSERT INTO `{$table}` ({$column_list}) VALUES " . implode( ', ', $placeholders ) . '
				ON DUPLICATE KEY UPDATE ' . implode( ', ', $updates );

		return false !== $this->wpdb->query( $this->wpdb->prepare( $sql, $args ) );
	}

	private static function pickerDefaultKeys() {
		$keys = [];
		foreach ( LanguagePresetsDefaultPairs::data() as $entry ) {
			$code = isset( $entry[0] ) ? trim( (string) $entry[0] ) : '';
			if ( '' === $code ) {
				continue;
			}
			$country = isset( $entry[1] ) && null !== $entry[1] ? strtoupper( trim( (string) $entry[1] ) ) : '';

			$keys[ $code . '|' . $country ] = true;
		}

		return $keys;
	}

	private function computePairData( array $m2m ) {
		$presets    = [];
		$countries  = [];
		foreach ( $m2m as $code => $data ) {
			$presets[ $code ] = [
				'script'        => $data['script'],
				'language'      => $data['language'],
				'type'          => $data['type'],
				'country_mode'  => $data['country_mode'],
				'language_flag' => $data['language_flag'],
			];
			$default   = null !== $data['default'] ? strtoupper( (string) $data['default'] ) : null;
			$overrides = isset( $data['locale_by_country'] ) && is_array( $data['locale_by_country'] ) ? $data['locale_by_country'] : [];
			$rows      = [];
			foreach ( (array) $data['allowed'] as $cc ) {
				$cc = strtoupper( trim( (string) $cc ) );
				if ( '' !== $cc ) {
					$rows[] = [ 'country_code' => $cc, 'is_default' => ( $cc === $default ) ? 1 : 0, 'wp_locale' => isset( $overrides[ $cc ] ) ? $overrides[ $cc ] : null ];
				}
			}
			if ( null !== $default && ! in_array( $default, array_column( $rows, 'country_code' ), true ) ) {
				$rows[] = [ 'country_code' => $default, 'is_default' => 1, 'wp_locale' => isset( $overrides[ $default ] ) ? $overrides[ $default ] : null ];
			}
			$countries[ $code ] = $rows;
		}

		$localeMap = function_exists( 'icl_get_languages_locales' ) ? icl_get_languages_locales() : [];
		$pairCodes = [];
		foreach ( $m2m as $presetCode => $presetData ) {
			if ( isset( $presetData['wp_code'] ) && '' !== (string) $presetData['wp_code'] ) {
				$localeMap[ $presetCode ] = (string) $presetData['wp_code'];
			}
			$pairCodes[ $presetCode ] = isset( $presetData['pair_codes'] ) ? (array) $presetData['pair_codes'] : [];
		}

		return PresetPairCodes::compute( $presets, $countries, $localeMap, $pairCodes );
	}

	private function columnExists2( $column ) {
		$wpdb = $this->wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare( "SHOW COLUMNS FROM `{$wpdb->prefix}icl_language_preset_countries` LIKE %s", $column )
		);
	}

	public function languageFlag( $code, $script = null, $language = null ) {
		$code     = strtolower( trim( (string) $code ) );
		$manifest = FlagManifest::instance();

		$file = $manifest->languageFile( $code );
		if ( null !== $file ) {
			return $file;
		}

		$language = $language !== null && '' !== (string) $language
			? strtolower( trim( (string) $language ) )
			: LanguagePairKey::languageSubtag( $code, $script );

		return $language !== $code ? $manifest->languageFile( $language ) : null;
	}

	public static function normalize( array $row ) {
		$code = isset( $row['code'] ) ? trim( (string) $row['code'] ) : '';
		if ( '' === $code ) {
			return null;
		}

		$type  = isset( $row['type'] ) ? (string) $row['type'] : 'national';
		$mode  = isset( $row['country_mode'] ) ? (string) $row['country_mode'] : 'required';
		$type  = in_array( $type, [ 'national', 'regional', 'international' ], true ) ? $type : 'national';
		$mode  = in_array( $mode, [ 'required', 'optional', 'none' ], true ) ? $mode : 'required';
		$allow = isset( $row['allowed_countries'] ) && is_array( $row['allowed_countries'] )
			? array_values( $row['allowed_countries'] )
			: [];

		$defaultCountry = isset( $row['default_country'] ) && '' !== (string) $row['default_country'] ? (string) $row['default_country'] : null;
		$script         = isset( $row['script'] ) && '' !== (string) $row['script'] ? (string) $row['script'] : null;

		$language = isset( $row['language'] ) && '' !== (string) $row['language']
			? (string) $row['language']
			: LanguagePairKey::languageSubtag( $code, $script );

		$wpCode = isset( $row['wp_code'] ) && '' !== (string) $row['wp_code']
			? (string) $row['wp_code']
			: self::deriveWpCode( $code, $defaultCountry, $script, $language );

		$tier = isset( $row['visibility_tier'] ) ? (string) $row['visibility_tier'] : 'all';
		$tier = in_array( $tier, [ 'default', 'all', 'all_disabled' ], true ) ? $tier : 'all';

		$localeByCountry = [];
		if ( isset( $row['wp_locale_by_country'] ) && is_array( $row['wp_locale_by_country'] ) ) {
			foreach ( $row['wp_locale_by_country'] as $cc => $wpLocale ) {
				$cc = strtoupper( trim( (string) $cc ) );
				if ( '' !== $cc && '' !== (string) $wpLocale ) {
					$localeByCountry[ $cc ] = (string) $wpLocale;
				}
			}
		}

		$pairCodes = [];
		if ( isset( $row['pair_codes'] ) && is_array( $row['pair_codes'] ) ) {
			foreach ( $row['pair_codes'] as $cc => $pairCode ) {
				if ( '' !== (string) $pairCode ) {
					$pairCodes[ strtoupper( trim( (string) $cc ) ) ] = strtolower( trim( (string) $pairCode ) );
				}
			}
		}

		return [
			'code'             => $code,
			'english_name'     => isset( $row['english_name'] ) ? (string) $row['english_name'] : $code,
			'script'           => $script,
			'language'         => $language,
			'type'             => $type,
			'country_mode'     => $mode,
			'wp_code'          => $wpCode,
			'major'            => ! empty( $row['major'] ) ? 1 : 0,
			'visibility_tier'  => $tier,
			'allowed'          => $allow,
			'default_country'  => $defaultCountry,
			'locale_by_country' => $localeByCountry,
			'pair_codes'       => $pairCodes,
		];
	}

	public static function deriveWpCode( $code, $defaultCountry = null, $script = null, $language = null ) {
		$code = strtolower( trim( (string) $code ) );
		$lang = $language !== null && '' !== (string) $language
			? strtolower( trim( (string) $language ) )
			: LanguagePairKey::languageSubtag( $code, $script );

		$special = [
			'zh-hans' => 'zh_CN',
			'zh-hant' => 'zh_TW',
			'sr-cyrl' => 'sr_RS',
			'sr-latn' => 'sr_RS',
			'pa-arab' => 'pa_PK',
			'pa-guru' => 'pa_IN',
		];
		if ( isset( $special[ $code ] ) ) {
			return $special[ $code ];
		}

		if ( 'en' === $lang && empty( $defaultCountry ) ) {
			return 'en_US';
		}

		if ( ! empty( $defaultCountry ) ) {
			return $lang . '_' . strtoupper( (string) $defaultCountry );
		}

		return $lang;
	}

	private function columnExists( $column ) {
		$wpdb  = $this->wpdb;
		$table = $this->table();
		$found = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW COLUMNS FROM `' . esc_sql( $table ) . '` LIKE %s',
				$column
			)
		);

		return is_string( $found ) && '' !== $found;
	}
}
