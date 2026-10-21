<?php

namespace WPML\LanguageEditor\Presets;

use WPML\LanguageEditor\Flags\FlagFile;
use WPML\LanguageEditor\Flags\FlagManifest;
use WPML\Languages\RemovedLanguages;
use WPML\LanguageEditor\LanguageCodeResolution;
use WPML\Upgrade\Commands\CreateLanguagePresetsTable;
use WPML\Upgrade\Commands\CreateLanguagePresetCountriesTable;

class AteCatalogueSync {

	const UNVOUCHED_COLUMN = 'unvouched_at';

	const OFFERABLE_FLAGS_CAP = 191;

	private $wpdb;

	private $writeFailed = false;

	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public static function create() {
		global $wpdb;

		return new self( $wpdb );
	}

	private function table() {
		return $this->wpdb->prefix . CreateLanguagePresetsTable::TABLE_NAME;
	}

	private function countriesTable() {
		return $this->wpdb->prefix . CreateLanguagePresetCountriesTable::TABLE_NAME;
	}

	private function languagesTable() {
		return $this->wpdb->prefix . 'icl_languages';
	}

	public function reconcileWith( array $feed ) {
		$presets = $this->loadPresets();

		$byCode = [];
		foreach ( $presets as $code => $row ) {
			$byCode[ strtolower( $code ) ] = $code;
		}

		$addedCountriesPerCode = [];
		$flagGrants            = [];
		$unmatched             = [];
		$checked               = 0;

		foreach ( $feed as $entry ) {
			$iso       = (string) self::prop( $entry, 'iso' );
			$defaultLc = strtolower( (string) self::prop( $entry, 'wpml_default_code' ) );
			if ( '' === $iso ) {
				continue;
			}
			++$checked;

			if ( '' !== $defaultLc ) {
				$this->collectOfferableFlags( $entry, [ 'code' => $defaultLc ], $flagGrants );
				if ( ! isset( $byCode[ $defaultLc ] ) ) {
					$unmatched[] = $iso;
				}
				continue;
			}

			list( $base, $country ) = self::splitVariant( $iso );
			if ( null === $country || ! isset( $byCode[ $base ] ) ) {
				$unmatched[] = $iso;
				continue;
			}

			$canonical = $byCode[ $base ];
			$existing  = (array) $presets[ $canonical ]['allowed'];
			$country   = strtoupper( $country );
			if ( ! in_array( $country, $existing, true ) ) {
				$addedCountriesPerCode[ $canonical ][ $country ] = strtolower( $iso );
			}
			$this->collectOfferableFlags(
				$entry,
				[ 'preset_code' => $canonical, 'country_code' => $country ],
				$flagGrants
			);
		}

		$addedCountries = $this->applyAddedCountries( $presets, $addedCountriesPerCode );
		$flags          = $this->applyOfferableFlags( $flagGrants );

		LanguageCodeResolution::resetCache();

		return [
			'checked'        => $checked,
			'addedCountries' => $addedCountries,
			'grantedFlags'   => $flags['grown'],
			'cappedFlags'    => $flags['capped'],
			'unmatched'      => array_values( array_unique( $unmatched ) ),
		];
	}

	private function collectOfferableFlags( $entry, array $where, array &$grants ) {
		$files = self::prop( $entry, 'offerable_flags' );
		if ( ! is_array( $files ) ) {
			return;
		}
		$valid = [];
		foreach ( $files as $file ) {
			$raw        = is_scalar( $file ) ? (string) $file : '';
			$normalized = FlagFile::normalize( $raw );
			if ( '' === $normalized ) {
				FlagFile::reportRejected( $raw, implode( '/', array_map( 'strval', array_values( $where ) ) ) );
				continue;
			}
			$valid[] = $normalized;
		}
		if ( $valid ) {
			$grants[] = [ $where, array_values( array_unique( $valid ) ) ];
		}
	}

	private function applyOfferableFlags( array $grants ) {
		$report = [ 'grown' => 0, 'capped' => 0 ];

		if ( ! $grants ) {
			return $report;
		}
		$m2m = $this->countriesTable();
		$columns = (array) $this->wpdb->get_col( "SHOW COLUMNS FROM `{$m2m}`" );
		if ( ! in_array( 'offerable_flags', $columns, true ) ) {
			return $report;
		}

		foreach ( $grants as $grant ) {
			list( $where, $files ) = $grant;

			$conditions = [];
			$values     = [];
			foreach ( $where as $column => $value ) {
				$conditions[] = "`{$column}` = %s";
				$values[]     = $value;
			}
			$sql = 'SELECT `offerable_flags` FROM `' . $m2m . '` WHERE ' . implode( ' AND ', $conditions ) . ' LIMIT 1';
			$current = $this->wpdb->get_row( $this->wpdb->prepare( $sql, $values ), ARRAY_A );
			if ( null === $current ) {
				continue;
			}

			$stored = isset( $current['offerable_flags'] ) ? (string) $current['offerable_flags'] : '';
			$list   = '' === trim( $stored ) ? [] : array_map( 'trim', explode( ',', $stored ) );
			sort( $list );

			$merged = $list;
			foreach ( $files as $file ) {
				if ( in_array( $file, $merged, true ) ) {
					continue;
				}
				$candidate   = $merged;
				$candidate[] = $file;
				sort( $candidate );
				if ( strlen( implode( ',', $candidate ) ) > self::OFFERABLE_FLAGS_CAP ) {
					++$report['capped'];
					continue;
				}
				$merged = $candidate;
			}

			if ( $merged === $list ) {
				continue;
			}

			$updated = $this->wpdb->update(
				$m2m,
				[ 'offerable_flags' => implode( ',', $merged ) ],
				$where,
				[ '%s' ],
				array_fill( 0, count( $where ), '%s' )
			);
			if ( false !== $updated ) {
				++$report['grown'];
			}
		}

		return $report;
	}


	public function applyAmsFeed( CatalogueFeed $feed ) {
		$this->writeFailed = false;

		$report = [
			'addedPresets'   => 0,
			'addedCountries' => 0,
			'grantedFlags'   => 0,
			'cappedFlags'    => 0,
			'applyFailed'    => false,
			'stoodDown'      => false,
		];

		$presetsTable = $this->table();
		$m2m          = $this->countriesTable();
		if ( ! $this->tableExists( $presetsTable ) || ! $this->tableExists( $m2m ) ) {
			$report['stoodDown'] = true;

			return $report;
		}

		$authoritative = $feed->isFullAuthority();
		$catalogue     = $this->loadCatalogue( $presetsTable, $m2m );
		$presetColumns = $this->columnsOf( $presetsTable );
		$pairColumns   = $this->columnsOf( $m2m );

		$grants  = [];
		$changed = false;

		foreach ( $feed->entries as $entry ) {
			$lc    = $entry['code'];
			$known = isset( $catalogue['presets'][ $lc ] );

			list( $language, $script, $isRegional ) = self::presetIdentity(
				$known ? $catalogue['presets'][ $lc ] : [],
				$entry
			);

			if ( $known ) {
				$canonical = (string) $catalogue['presets'][ $lc ]['code'];
				if ( $authoritative && $this->updatePresetFields( $presetsTable, $presetColumns, $catalogue['presets'][ $lc ], $entry ) ) {
					$changed = true;
				}
			} else {
				if ( ! $this->insertPreset( $presetsTable, $presetColumns, $entry ) ) {
					continue;
				}
				$canonical                   = $entry['code'];
				$catalogue['presets'][ $lc ] = [ 'code' => $canonical ];
				$catalogue['pairs'][ $lc ]   = [];
				++$report['addedPresets'];
				$changed = true;
			}

			$languageFlag = self::manifestLanguageFlag( $canonical );

			$pairs = isset( $catalogue['pairs'][ $lc ] ) ? $catalogue['pairs'][ $lc ] : [];
			$order = count( $pairs );

			foreach ( $entry['countries'] as $index => $country ) {
				$cc = $country['country_code'];

				$feedFlag    = isset( $country['flag'] ) ? $country['flag'] : null;
				$derivedFlag = PresetPairCodes::defaultFlag( $language, $script, $cc, $languageFlag, $isRegional );

				$pickerDefault = array_key_exists( 'picker_default', $country ) ? $country['picker_default'] : null;

				$derivedLocale = PresetPairCodes::defaultLocale( $canonical, $cc );

				if ( ! isset( $pairs[ $cc ] ) ) {
					$isDefault = $known ? 0 : ( ! empty( $country['is_default'] ) ? 1 : 0 );
					$sortOrder = $known
						? $order
						: ( null === $country['sort_order'] ? (int) $index : (int) $country['sort_order'] );

					$flag = null !== $feedFlag ? self::resolveFeedFlag( $feedFlag ) : $derivedFlag;
					$locale = null !== $country['wp_locale'] ? $country['wp_locale'] : $derivedLocale;

					if ( $this->insertPairRow( $m2m, $pairColumns, $canonical, $cc, $isDefault, $sortOrder, $country['pair_code'], $locale, $flag, $pickerDefault ) ) {
						$pairs[ $cc ] = [ 'preset_code' => $canonical, 'country_code' => $cc, 'code' => $country['pair_code'], 'default_locale' => $locale, 'flag' => $flag, 'picker_default' => $pickerDefault ? 1 : 0 ];
						++$report['addedCountries'];
						++$order;
						$changed = true;
					}
				} else {
					if ( $authoritative && $this->updatePairLocale( $m2m, $pairColumns, $pairs[ $cc ], $country, $derivedLocale ) ) {
						$changed = true;
					}
					if ( $authoritative && $this->updatePairCode( $m2m, $pairColumns, $pairs[ $cc ], $country ) ) {
						$changed = true;
					}
					if ( $authoritative && $this->updatePairFlag( $m2m, $pairColumns, $pairs[ $cc ], $feedFlag, $derivedFlag ) ) {
						$changed = true;
					}
					if ( $authoritative && $this->updatePairPickerDefault( $m2m, $pairColumns, $pairs[ $cc ], $pickerDefault ) ) {
						$changed = true;
					}
				}

				if ( $country['offerable_flags'] ) {
					$this->collectOfferableFlags(
						[ 'offerable_flags' => $country['offerable_flags'] ],
						[ 'preset_code' => $canonical, 'country_code' => $cc ],
						$grants
					);
				}
			}

			$catalogue['pairs'][ $lc ] = $pairs;
		}

		$flags                  = $this->applyOfferableFlags( $grants );
		$report['grantedFlags'] = $flags['grown'];
		$report['cappedFlags']  = $flags['capped'];
		$changed                = $changed || $report['grantedFlags'] > 0;

		if ( $changed ) {
			LanguageCodeResolution::resetCache();
		}

		$report['applyFailed'] = $this->writeFailed;

		return $report;
	}

	public function applyVouchState( CatalogueFeed $feed ) {
		$this->writeFailed = false;

		$report = [
			'vouched'     => 0,
			'unvouched'   => 0,
			'applyFailed' => false,
			'stoodDown'   => false,
		];

		$presetsTable = $this->table();
		$m2m          = $this->countriesTable();
		if ( ! $this->tableExists( $presetsTable ) || ! $this->tableExists( $m2m ) ) {
			$report['stoodDown'] = true;

			return $report;
		}
		if ( ! $this->columnExists( $presetsTable, self::UNVOUCHED_COLUMN )
			|| ! $this->columnExists( $m2m, self::UNVOUCHED_COLUMN ) ) {
			$report['stoodDown'] = true;

			return $report;
		}

		list( $servedCodes, $servedPairs ) = self::servedSets( $feed );

		if ( empty( $servedCodes ) ) {
			$report['stoodDown'] = true;

			return $report;
		}

		$report['vouched'] = $this->clearVouch( $presetsTable, $m2m, $servedCodes, $servedPairs );

		if ( $feed->isFullAuthority() ) {
			if ( empty( $servedPairs ) ) {
				$report['stoodDown'] = true;
			} else {
				list( $stamped, $stoodDown ) = $this->stampUnvouched( $presetsTable, $m2m, $servedCodes, $servedPairs );

				$report['unvouched'] = $stamped;
				$report['stoodDown'] = $stoodDown;
			}
		}

		if ( $report['vouched'] > 0 || $report['unvouched'] > 0 ) {
			LanguageCodeResolution::resetCache();
		}

		$report['applyFailed'] = $this->writeFailed;

		return $report;
	}

	private static function servedSets( CatalogueFeed $feed ) {
		$codes = [];
		$pairs = [];
		foreach ( $feed->entries as $entry ) {
			$code           = (string) $entry['code'];
			$codes[ $code ] = true;
			foreach ( $entry['countries'] as $country ) {
				$pairs[ $code . '|' . $country['country_code'] ] = [ $code, (string) $country['country_code'] ];
			}
		}

		return [ array_keys( $codes ), array_values( $pairs ) ];
	}

	private function clearVouch( $presetsTable, $m2m, array $servedCodes, array $servedPairs ) {
		$cleared = $this->write(
			"UPDATE `{$presetsTable}` SET `" . self::UNVOUCHED_COLUMN . '` = NULL'
			. ' WHERE `' . self::UNVOUCHED_COLUMN . '` IS NOT NULL'
			. ' AND LOWER(`code`) IN ( ' . self::placeholders( $servedCodes ) . ' )',
			$servedCodes
		);

		if ( ! empty( $servedPairs ) ) {
			list( $tupleList, $tupleArgs ) = self::tuples( $servedPairs );
			$cleared                      += $this->write(
				"UPDATE `{$m2m}` SET `" . self::UNVOUCHED_COLUMN . '` = NULL'
				. ' WHERE `' . self::UNVOUCHED_COLUMN . '` IS NOT NULL'
				. " AND ( LOWER(`preset_code`), UPPER(`country_code`) ) IN ( {$tupleList} )",
				$tupleArgs
			);
		}

		return $cleared;
	}

	private function stampUnvouched( $presetsTable, $m2m, array $servedCodes, array $servedPairs ) {
		if ( empty( $servedCodes ) || empty( $servedPairs ) ) {
			return [ 0, true ];
		}

		$siteCodes = $this->siteProvenanceCodes();

		$stamp         = gmdate( 'Y-m-d H:i:s' );
		$mintedPresets = $this->presetsWithMintedPairs( $m2m, $servedPairs, $siteCodes );

		$conditions = [
			'`' . self::UNVOUCHED_COLUMN . '` IS NULL',
			'LOWER(`code`) NOT IN ( ' . self::placeholders( $servedCodes ) . ' )',
		];
		$args = array_merge( [ $stamp ], $servedCodes );

		if ( ! empty( $siteCodes ) ) {
			$conditions[] = 'LOWER(`code`) NOT IN ( ' . self::placeholders( $siteCodes ) . ' )';
			$args         = array_merge( $args, $siteCodes );
		}

		if ( ! empty( $mintedPresets ) ) {
			$conditions[] = 'LOWER(`code`) NOT IN ( ' . self::placeholders( $mintedPresets ) . ' )';
			$args         = array_merge( $args, $mintedPresets );
		}

		$stamped = $this->write(
			"UPDATE `{$presetsTable}` SET `" . self::UNVOUCHED_COLUMN . '` = %s WHERE ' . implode( ' AND ', $conditions ),
			$args
		);

		list( $servedList, $servedArgs ) = self::tuples( $servedPairs );

		$conditions = [
			'`' . self::UNVOUCHED_COLUMN . '` IS NULL',
			"( LOWER(`preset_code`), UPPER(`country_code`) ) NOT IN ( {$servedList} )",
		];
		$args = array_merge( [ $stamp ], $servedArgs );

		if ( ! empty( $siteCodes ) ) {
			$conditions[] = "( `code` IS NULL OR `code` = '' OR LOWER(`code`) NOT IN ( "
				. self::placeholders( $siteCodes ) . ' ) )';
			$args         = array_merge( $args, $siteCodes );
		}

		$stamped += $this->write(
			"UPDATE `{$m2m}` SET `" . self::UNVOUCHED_COLUMN . '` = %s WHERE ' . implode( ' AND ', $conditions ),
			$args
		);

		return [ $stamped, false ];
	}

	private function siteProvenanceCodes() {
		$languages = $this->languagesTable();

		$active = (array) $this->wpdb->get_col( "SELECT `code` FROM `{$languages}` WHERE `active` = 1" );

		return self::normalize(
			array_merge( $active, array_keys( RemovedLanguages::withContent() ) ),
			'strtolower'
		);
	}

	private function presetsWithMintedPairs( $m2m, array $servedPairs, array $siteCodes ) {
		$presets = [];
		foreach ( $servedPairs as $pair ) {
			$presets[ $pair[0] ] = true;
		}

		if ( ! empty( $siteCodes ) ) {
			$sql = "SELECT DISTINCT LOWER(`preset_code`) FROM `{$m2m}`"
				. " WHERE `code` IS NOT NULL AND `code` <> '' AND LOWER(`code`) IN ( "
				. self::placeholders( $siteCodes ) . ' )';
			$rows = $this->wpdb->get_col( $this->wpdb->prepare( $sql, $siteCodes ) );

			foreach ( self::normalize( $rows, 'strtolower' ) as $code ) {
				$presets[ $code ] = true;
			}
		}

		return array_keys( $presets );
	}

	private static function tuples( array $pairs ) {
		$args = [];
		foreach ( $pairs as $pair ) {
			$args[] = (string) $pair[0];
			$args[] = (string) $pair[1];
		}

		return [ implode( ', ', array_fill( 0, count( $pairs ), '( %s, %s )' ) ), $args ];
	}

	private function write( $sql, array $args ) {
		$result = $this->wpdb->query( $this->wpdb->prepare( $sql, $args ) );

		if ( false === $result ) {
			$this->writeFailed = true;

			return 0;
		}

		return (int) $result;
	}

	private function loadCatalogue( $presetsTable, $m2m ) {
		$presets = [];
		foreach ( (array) $this->wpdb->get_results( "SELECT * FROM `{$presetsTable}`", ARRAY_A ) as $row ) {
			$code = strtolower( trim( (string) ( isset( $row['code'] ) ? $row['code'] : '' ) ) );
			if ( '' !== $code ) {
				$presets[ $code ] = $row;
			}
		}

		$pairs = [];
		foreach ( (array) $this->wpdb->get_results( "SELECT * FROM `{$m2m}`", ARRAY_A ) as $row ) {
			$code = strtolower( trim( (string) ( isset( $row['preset_code'] ) ? $row['preset_code'] : '' ) ) );
			if ( '' === $code ) {
				continue;
			}
			$country                    = strtoupper( trim( (string) ( isset( $row['country_code'] ) ? $row['country_code'] : '' ) ) );
			$pairs[ $code ][ $country ] = $row;
		}

		return [ 'presets' => $presets, 'pairs' => $pairs ];
	}

	private function insertPreset( $table, array $columns, array $entry ) {
		$row     = [ 'code' => $entry['code'] ];
		$formats = [ '%s' ];

		$strings = [ 'english_name', 'script', 'language', 'type', 'country_mode', 'wp_code', 'visibility_tier' ];
		foreach ( $strings as $column ) {
			if ( in_array( $column, $columns, true ) && null !== $entry[ $column ] ) {
				$row[ $column ] = $entry[ $column ];
				$formats[]      = '%s';
			}
		}

		$languageFlag = self::presetLanguageFlag( $entry, null );
		if ( in_array( 'language_flag', $columns, true ) && null !== $languageFlag ) {
			$row['language_flag'] = $languageFlag;
			$formats[]            = '%s';
		}
		if ( in_array( 'major', $columns, true ) && null !== $entry['major'] ) {
			$row['major'] = (int) $entry['major'];
			$formats[]    = '%d';
		}
		if ( in_array( 'rtl', $columns, true ) && array_key_exists( 'rtl', $entry ) && null !== $entry['rtl'] ) {
			$row['rtl'] = (int) $entry['rtl'];
			$formats[]  = '%d';
		}

		$inserted = $this->wpdb->insert( $table, $row, $formats );
		if ( false === $inserted ) {
			$this->writeFailed = true;

			return false;
		}

		return true;
	}

	private function updatePresetFields( $table, array $columns, array $current, array $entry ) {
		$row     = [];
		$formats = [];

		$strings = [ 'english_name', 'type', 'country_mode', 'visibility_tier', 'wp_code' ];
		foreach ( $strings as $column ) {
			if ( ! in_array( $column, $columns, true ) || null === $entry[ $column ] ) {
				continue;
			}
			$stored = isset( $current[ $column ] ) ? (string) $current[ $column ] : '';
			if ( $stored !== (string) $entry[ $column ] ) {
				$row[ $column ] = $entry[ $column ];
				$formats[]      = '%s';
			}
		}

		if ( in_array( 'language', $columns, true ) ) {
			$language = self::presetLanguageFill( $entry, isset( $current['language'] ) ? $current['language'] : null );
			if ( null !== $language ) {
				$row['language'] = $language;
				$formats[]       = '%s';
			}
		}
		if ( in_array( 'language_flag', $columns, true ) ) {
			$storedFlag   = isset( $current['language_flag'] ) ? $current['language_flag'] : null;
			$languageFlag = self::presetLanguageFlag( $entry, $storedFlag );
			if ( null !== $languageFlag && (string) $storedFlag !== $languageFlag ) {
				$row['language_flag'] = $languageFlag;
				$formats[]            = '%s';
			}
		}
		if ( in_array( 'major', $columns, true ) && null !== $entry['major'] ) {
			$stored = isset( $current['major'] ) ? (int) $current['major'] : 0;
			if ( $stored !== (int) $entry['major'] ) {
				$row['major'] = (int) $entry['major'];
				$formats[]    = '%d';
			}
		}
		if ( in_array( 'rtl', $columns, true ) && array_key_exists( 'rtl', $entry ) && null !== $entry['rtl'] ) {
			$stored = isset( $current['rtl'] ) && null !== $current['rtl'] ? (int) $current['rtl'] : null;
			if ( $stored !== (int) $entry['rtl'] ) {
				$row['rtl'] = (int) $entry['rtl'];
				$formats[]  = '%d';
			}
		}

		if ( ! $row ) {
			return false;
		}

		$updated = $this->wpdb->update(
			$table,
			$row,
			[ 'code' => isset( $current['code'] ) ? $current['code'] : $entry['code'] ],
			$formats,
			[ '%s' ]
		);
		if ( false === $updated ) {
			$this->writeFailed = true;

			return false;
		}

		return $updated > 0;
	}

	private function insertPairRow( $m2m, array $columns, $presetCode, $country, $isDefault, $sortOrder, $pairCode = null, $wpLocale = null, $flag = null, $pickerDefault = null ) {
		if ( '' !== $country ) {
			$this->ensureCountry( $country );
		}

		$row = [
			'preset_code'  => $presetCode,
			'country_code' => $country,
			'is_default'   => (int) $isDefault,
			'sort_order'   => (int) $sortOrder,
		];
		$formats = [ '%s', '%s', '%d', '%d' ];

		$identity = [
			'code'           => null !== $pairCode && '' !== (string) $pairCode ? (string) $pairCode : null,
			'default_locale' => null !== $wpLocale && '' !== (string) $wpLocale ? (string) $wpLocale : null,
		];
		foreach ( $identity as $column => $value ) {
			if ( in_array( $column, $columns, true ) ) {
				$row[ $column ] = $value;
				$formats[]      = '%s';
			}
		}

		if ( in_array( 'flag', $columns, true ) ) {
			$row['flag'] = null === $flag || '' === (string) $flag ? FlagFile::NEUTRAL_GLOBE : (string) $flag;
			$formats[]   = '%s';
		}

		if ( in_array( 'picker_default', $columns, true ) ) {
			$row['picker_default'] = $pickerDefault ? 1 : 0;
			$formats[]             = '%d';
		}

		$inserted = $this->wpdb->insert( $m2m, $row, $formats );
		if ( false === $inserted ) {
			$this->writeFailed = true;

			return false;
		}

		return true;
	}

	private function updatePairLocale( $m2m, array $columns, array $current, array $country, $derived = null ) {
		if ( ! in_array( 'default_locale', $columns, true ) ) {
			return false;
		}
		if ( isset( $current['default_locale'] ) && '' !== (string) $current['default_locale'] ) {
			return false;
		}

		$value = null !== $country['wp_locale'] ? (string) $country['wp_locale'] : (string) $derived;
		if ( '' === $value ) {
			return false;
		}

		$updated = $this->wpdb->update(
			$m2m,
			[ 'default_locale' => $value ],
			[
				'preset_code'  => isset( $current['preset_code'] ) ? $current['preset_code'] : '',
				'country_code' => isset( $current['country_code'] ) ? $current['country_code'] : '',
			],
			[ '%s' ],
			[ '%s', '%s' ]
		);
		if ( false === $updated ) {
			$this->writeFailed = true;

			return false;
		}

		return $updated > 0;
	}

	private function updatePairCode( $m2m, array $columns, array $current, array $country ) {
		if ( ! isset( $country['pair_code'] ) || null === $country['pair_code'] || ! in_array( 'code', $columns, true ) ) {
			return false;
		}
		if ( isset( $current['code'] ) && '' !== (string) $current['code'] ) {
			return false;
		}

		$updated = $this->wpdb->update(
			$m2m,
			[ 'code' => (string) $country['pair_code'] ],
			[
				'preset_code'  => isset( $current['preset_code'] ) ? $current['preset_code'] : '',
				'country_code' => isset( $current['country_code'] ) ? $current['country_code'] : '',
			],
			[ '%s' ],
			[ '%s', '%s' ]
		);
		if ( false === $updated ) {
			$this->writeFailed = true;

			return false;
		}

		return $updated > 0;
	}

	private function updatePairFlag( $m2m, array $columns, array $current, $feedFlag, $derived ) {
		if ( ! in_array( 'flag', $columns, true ) ) {
			return false;
		}

		$stored = isset( $current['flag'] ) ? (string) $current['flag'] : '';

		if ( null !== $feedFlag ) {
			$value = self::resolveFeedFlag( $feedFlag );
		} elseif ( '' !== $stored ) {
			return false;
		} else {
			$value = (string) $derived;
		}

		if ( '' === $value || $value === $stored ) {
			return false;
		}

		$updated = $this->wpdb->update(
			$m2m,
			[ 'flag' => $value ],
			[
				'preset_code'  => isset( $current['preset_code'] ) ? $current['preset_code'] : '',
				'country_code' => isset( $current['country_code'] ) ? $current['country_code'] : '',
			],
			[ '%s' ],
			[ '%s', '%s' ]
		);
		if ( false === $updated ) {
			$this->writeFailed = true;

			return false;
		}

		return $updated > 0;
	}

	private function updatePairPickerDefault( $m2m, array $columns, array $current, $served ) {
		if ( ! in_array( 'picker_default', $columns, true ) ) {
			return false;
		}
		if ( null === $served ) {
			return false;
		}

		$value  = $served ? 1 : 0;
		$stored = isset( $current['picker_default'] ) ? (int) $current['picker_default'] : 0;
		if ( $value === $stored ) {
			return false;
		}

		$updated = $this->wpdb->update(
			$m2m,
			[ 'picker_default' => $value ],
			[
				'preset_code'  => isset( $current['preset_code'] ) ? $current['preset_code'] : '',
				'country_code' => isset( $current['country_code'] ) ? $current['country_code'] : '',
			],
			[ '%d' ],
			[ '%s', '%s' ]
		);
		if ( false === $updated ) {
			$this->writeFailed = true;

			return false;
		}

		return $updated > 0;
	}

	private static function presetLanguageFlag( array $entry, $stored ) {
		if ( null !== $entry['language_flag'] ) {
			return self::resolveFeedFlag( $entry['language_flag'] );
		}

		if ( null !== $stored && '' !== (string) $stored ) {
			return null;
		}

		$derived = self::manifestLanguageFlag( (string) $entry['code'] );

		return '' === $derived ? null : $derived;
	}

	private static function presetLanguageFill( array $entry, $stored ) {
		if ( null !== $stored && '' !== (string) $stored ) {
			return null;
		}

		$served = isset( $entry['language'] ) ? (string) $entry['language'] : '';

		return '' === $served ? null : $served;
	}

	private static function resolveFeedFlag( $name ) {
		return FlagFile::resolve( (string) $name );
	}

	private static function manifestLanguageFlag( $presetCode ) {
		return (string) FlagManifest::instance()->languageFile( $presetCode );
	}

	private static function presetIdentity( array $stored, array $entry ) {
		$pick = function ( $key ) use ( $stored, $entry ) {
			if ( isset( $stored[ $key ] ) && '' !== (string) $stored[ $key ] ) {
				return (string) $stored[ $key ];
			}

			return isset( $entry[ $key ] ) && null !== $entry[ $key ] ? (string) $entry[ $key ] : '';
		};

		$script = $pick( 'script' );

		return [ $pick( 'language' ), '' === $script ? null : $script, 'regional' === strtolower( $pick( 'type' ) ) ];
	}

	private static function placeholders( array $values ) {
		return implode( ', ', array_fill( 0, count( $values ), '%s' ) );
	}

	private static function normalize( $rows, $case ) {
		$out = [];
		foreach ( (array) $rows as $value ) {
			$value = call_user_func( $case, trim( (string) $value ) );
			if ( '' !== $value ) {
				$out[ $value ] = true;
			}
		}

		return array_keys( $out );
	}

	private function tableExists( $table ) {
		$exists = (string) $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		return 0 === strcasecmp( $exists, $table );
	}

	private function columnExists( $table, $column ) {
		return (bool) $this->wpdb->get_var( $this->wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $column ) );
	}

	private function columnsOf( $table ) {
		return array_map( 'strval', (array) $this->wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" ) );
	}

	private function applyAddedCountries( array $presets, array $addedPerCode ) {
		$total = 0;
		$m2m   = $this->countriesTable();
		$columns   = (array) $this->wpdb->get_col( "SHOW COLUMNS FROM `{$m2m}`" );
		$hasCode   = in_array( 'code', $columns, true );
		$hasFlag   = in_array( 'flag', $columns, true );
		$hasLocale = in_array( 'default_locale', $columns, true );

		foreach ( $addedPerCode as $code => $countries ) {
			$order = count( (array) $presets[ $code ]['allowed'] );
			$languageFlag = self::manifestLanguageFlag( (string) $code );
			foreach ( $countries as $country => $pairCode ) {
				$cc  = strtoupper( (string) $country );
				$this->ensureCountry( $cc );
				$row = [
					'preset_code'  => $code,
					'country_code' => $cc,
					'is_default'   => 0,
					'sort_order'   => $order,
				];
				$fmt = [ '%s', '%s', '%d', '%d' ];
				if ( $hasCode ) {
					$stated      = (string) $pairCode;
					$row['code'] = '' !== $stated ? $stated : (string) PresetPairCodes::defaultCode( $code, $cc );
					$fmt[]       = '%s';
				}
				if ( $hasLocale ) {
					$row['default_locale'] = PresetPairCodes::defaultLocale( $code, $cc );
					$fmt[]                 = '%s';
				}
				if ( $hasFlag ) {
					$row['flag'] = PresetPairCodes::defaultFlag( '', null, $cc, $languageFlag, false );
					$fmt[]       = '%s';
				}
				$result = $this->wpdb->insert( $m2m, $row, $fmt );
				if ( false !== $result ) {
					++$total;
					++$order;
				}
			}
		}

		return $total;
	}

	private function ensureCountry( $code ) {
		$table = $this->wpdb->prefix . \WPML\Upgrade\Commands\CreateCountriesTable::TABLE_NAME;
		$exists = (string) $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( 0 !== strcasecmp( $exists, $table ) ) {
			return;
		}
		$present = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT `code` FROM `{$table}` WHERE `code` = %s", $code ) );
		if ( null !== $present ) {
			return;
		}

		$flag = CountriesSeeder::create()->flagFor( $code );
		$name = function_exists( 'locale_get_display_region' ) ? (string) locale_get_display_region( '-' . $code, 'en' ) : $code;
		if ( '' === $name || strtoupper( $name ) === $code ) {
			$name = $code;
		}
		$this->wpdb->insert(
			$table,
			[
				'code'         => $code,
				'flag'         => '' === $flag ? null : $flag,
				'english_name' => $name,
			],
			[ '%s', '%s', '%s' ]
		);
	}

	private function loadPresets() {
		$table = $this->table();

		$exists = (string) $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( 0 !== strcasecmp( $exists, $table ) ) {
			return [];
		}

		$codes = $this->wpdb->get_col( "SELECT `code` FROM `{$table}`" );

		$m2m      = $this->countriesTable();
		$byPreset = [];
		$rows = $this->wpdb->get_results( "SELECT `preset_code`, `country_code` FROM `{$m2m}` ORDER BY `sort_order` ASC", ARRAY_A );
		foreach ( (array) $rows as $row ) {
			$byPreset[ $row['preset_code'] ][] = strtoupper( (string) $row['country_code'] );
		}

		$out = [];
		foreach ( (array) $codes as $code ) {
			$out[ $code ] = [ 'allowed' => isset( $byPreset[ $code ] ) ? $byPreset[ $code ] : [] ];
		}

		return $out;
	}

	public static function splitVariant( $iso ) {
		$parts = explode( '-', strtolower( $iso ) );
		$last  = end( $parts );

		if ( count( $parts ) >= 2 && 2 === strlen( $last ) && ctype_alpha( $last ) ) {
			array_pop( $parts );

			return [ implode( '-', $parts ), strtoupper( $last ) ];
		}

		return [ implode( '-', $parts ), null ];
	}

	private static function prop( $entry, $key ) {
		if ( is_array( $entry ) ) {
			return isset( $entry[ $key ] ) ? $entry[ $key ] : null;
		}

		return isset( $entry->{$key} ) ? $entry->{$key} : null;
	}
}
