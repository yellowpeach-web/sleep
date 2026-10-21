<?php

namespace WPML\LanguageEditor\ResetDefaults;

use WPML\LanguageEditor\Flags\FlagFile;
use WPML\LanguageEditor\LanguageCodeResolution;
use WPML\LanguageEditor\LanguageNames;

class Detector {

	const GROUP_FLAGS   = 'flags';
	const GROUP_LABELS  = 'labels';
	const GROUP_LOCALES = 'locales';

	const MAX_LISTED_LANGUAGES = 50;

	const MAX_LISTED_CELLS = 5;

	const ENGLISH_DISPLAY_COLUMN = 'en';

	const REASON_BUDGET       = 'budget_exhausted';
	const REASON_READ_FAILED  = 'read_failed';
	const REASON_NO_LABELS_TABLE = 'labels_table_missing';

	private $budget;

	private $reason = null;

	public function __construct( ?Budget $budget = null ) {
		$this->budget = $budget ? $budget : new Budget();
	}

	public function differences() {
		global $wpdb;

		$this->reason = null;
		$this->budget->start();

		LanguageNames::resetCache();

		$flags   = [];
		$labels  = [];
		$locales = [];
		$codes   = [];

		if ( ! is_object( $wpdb ) ) {
			$this->reason = self::REASON_READ_FAILED;

			return $this->payload( $flags, $labels, $locales, $codes );
		}

		$rows = $this->activeRows( $wpdb );
		if ( ! $rows ) {
			return $this->payload( $flags, $labels, $locales, $codes );
		}

		$flagRows    = $this->flagRows( $wpdb );
		$localeMap   = $this->localeMap( $wpdb );
		$storedNames = $this->storedNames( $wpdb );
		$pairLocales = $this->pairLocales( $wpdb, $rows );

		$displayCodes = array_column( $rows, 'code' );
		$nameOfCode   = array_column( $rows, 'english_name', 'code' );

		foreach ( $rows as $row ) {
			if ( $this->budget->exhausted() ) {
				$this->reason = self::REASON_BUDGET;
				break;
			}

			$code     = (string) $row['code'];
			$identity = $this->identity( $code );

			if ( ! empty( $row['is_custom'] ) || null === $identity ) {
				continue;
			}

			$flag = $this->flagDifference( $row, $identity, $flagRows );
			if ( null !== $flag ) {
				$flags[]              = $flag;
				$codes[ $flag['code'] ] = true;
			}

			$label = $this->labelDifference( $row, $displayCodes, $nameOfCode, $storedNames );
			if ( null !== $label ) {
				$labels[]               = $label;
				$codes[ $label['code'] ] = true;
			}

			$locale = $this->localeDifference( $row, $identity, $localeMap, $pairLocales );
			if ( null !== $locale ) {
				$locales[]                = $locale;
				$codes[ $locale['code'] ] = true;
			}
		}

		return $this->payload( $flags, $labels, $locales, $codes );
	}

	private function flagDifference( array $row, array $identity, array $flagRows ) {
		$code        = (string) $row['code'];
		$defaultFlag = isset( $identity['pair_flag'] ) ? (string) $identity['pair_flag'] : '';

		if ( '' === $defaultFlag ) {
			return null;
		}

		$stored      = isset( $flagRows[ $code ] ) ? $flagRows[ $code ] : null;
		$currentFlag = null !== $stored ? (string) $stored['flag'] : '';
		$uploaded    = null !== $stored && 1 === (int) $stored['from_template'];

		$differs = $uploaded
			|| null === $stored
			|| FlagFile::normalize( $currentFlag ) !== FlagFile::normalize( $defaultFlag );

		if ( ! $differs ) {
			return null;
		}

		return [
			'code'       => $code,
			'name'       => (string) $row['english_name'],
			'current'    => '' !== $currentFlag
				? $currentFlag
				/* translators: Stands in for a flag file name in the "Reset language defaults" dialog of the WPML Languages editor, for a language that has no flag stored at all. */
				: __( 'no flag', 'sitepress' ),
			'default'    => $defaultFlag,
			'currentUrl' => $this->flagUrl( $currentFlag, $uploaded ),
			'defaultUrl' => $this->flagUrl( $defaultFlag, false ),
		];
	}

	private function labelDifference( array $row, array $displayCodes, array $nameOfCode, array $storedNames ) {
		$code    = (string) $row['code'];
		$shipped = $this->shippedLabels( $code );

		$cells    = [];
		$nameable = 0;
		$missing  = 0;

		foreach ( $displayCodes as $display ) {
			$display = (string) $display;

			if ( ! $this->measurableColumn( $code, $display ) ) {
				continue;
			}

			$default = $this->catalogueName( $code, $display, null );
			if ( null === $default || '' === $default ) {
				continue;
			}

			$nameable ++;

			$stored = isset( $storedNames[ $code ][ $display ] ) ? (string) $storedNames[ $code ][ $display ] : null;

			if ( null !== $stored && $this->isDefaultLabel( $stored, $code, $display, $default, $shipped ) ) {
				continue;
			}

			if ( null === $stored ) {
				$missing ++;
			}

			$cells[] = [
				'display'     => $display,
				'displayName' => isset( $nameOfCode[ $display ] ) ? (string) $nameOfCode[ $display ] : $display,
				'current'     => $stored,
				'default'     => $default,
				'missing'     => null === $stored,
			];
		}

		if ( ! $cells ) {
			return null;
		}

		return [
			'code'      => $code,
			'name'      => (string) $row['english_name'],
			'nameable'  => $nameable,
			'edited'    => count( $cells ) - $missing,
			'missing'   => $missing,
			'cellCount' => count( $cells ),
			'cells'     => $cells,
		];
	}

	private function localeDifference( array $row, array $identity, array $localeMap, array $pairLocales ) {
		$code    = (string) $row['code'];
		$country = strtoupper( trim( (string) $row['country'] ) );

		if ( '' === $country ) {
			return null;
		}

		$key = $this->pairKey( (string) $identity['preset_code'], $country );

		if ( ! isset( $pairLocales[ $key ] ) || '' === $pairLocales[ $key ] ) {
			return null;
		}

		$pairLocale = (string) $pairLocales[ $key ];
		$rowLocale  = (string) $row['default_locale'];
		$mapLocale  = isset( $localeMap[ $code ] ) ? (string) $localeMap[ $code ] : '';

		if ( $rowLocale === $pairLocale && $mapLocale === $pairLocale ) {
			return null;
		}

		return [
			'code'      => $code,
			'name'      => (string) $row['english_name'],
			'rowLocale' => $rowLocale,
			'mapLocale' => $mapLocale,
			'default'   => $pairLocale,
			'current'   => $rowLocale === $mapLocale
				? $rowLocale
				: sprintf(
					/* translators: Names the two places a WordPress locale is stored, when they disagree, in the "Reset language defaults" dialog of the WPML Languages editor. %1$s: the locale on the language row. %2$s: the locale in WPML's locale map. */
					__( '%1$s (language row) / %2$s (locale map)', 'sitepress' ),
					'' !== $rowLocale
						/* translators: Stands in for a WordPress locale that is not stored at all, in the "Reset language defaults" dialog of the WPML Languages editor. */
						? $rowLocale : __( 'missing', 'sitepress' ),
					'' !== $mapLocale
						/* translators: Stands in for a WordPress locale that is not stored at all, in the "Reset language defaults" dialog of the WPML Languages editor. */
						? $mapLocale : __( 'missing', 'sitepress' )
				),
		];
	}

	private function isDefaultLabel( $stored, $code, $display, $default, array $shipped ) {
		$base = LanguageNames::baseDisplayCode( $display );

		$candidates = array_filter(
			[
				$default,
				$this->catalogueName( $code, $display, false ),
				$this->catalogueName( $code, $display, true ),
				isset( $shipped[ $display ] ) ? $shipped[ $display ] : null,
				'' !== $base && isset( $shipped[ $base ] ) ? $shipped[ $base ] : null,
			],
			function ( $candidate ) {
				return is_string( $candidate ) && '' !== $candidate;
			}
		);

		return in_array( (string) $stored, $candidates, true );
	}

	private function measurableColumn( $code, $display ) {
		return LanguageNames::fitsCodeCap( $code )
			&& LanguageNames::fitsCodeCap( $display );
	}

	protected function shippedLabels( $code ) {
		if ( ! function_exists( 'icl_get_languages_codes' ) || ! function_exists( 'icl_get_languages_names' ) ) {
			return [];
		}

		$codes  = icl_get_languages_codes();
		$names  = icl_get_languages_names();
		$source = array_search( (string) $code, $codes, true );

		if ( false === $source || ! isset( $names[ $source ]['tr'] ) ) {
			return [];
		}

		$out = [];
		foreach ( (array) $names[ $source ]['tr'] as $displayName => $localized ) {
			if ( 0 === strpos( (string) $displayName, 'Norwegian Bokm' ) ) {
				$displayName = 'Norwegian Bokmål';
			}
			if ( isset( $codes[ $displayName ] ) && '' !== trim( (string) $localized ) ) {
				$out[ $codes[ $displayName ] ] = (string) $localized;
			}
		}

		return $out;
	}

	protected function identity( $code ) {
		return LanguageCodeResolution::resolve( $code );
	}

	protected function catalogueName( $code, $display, $qualify ) {
		return LanguageNames::nameFor( $code, $display, $qualify );
	}

	private function flagUrl( $name, $uploaded ) {
		$name = trim( (string) $name );

		if ( '' === $name || '@code' === $name ) {
			return '';
		}

		if ( $uploaded ) {
			$upload = wp_upload_dir();

			return ! empty( $upload['baseurl'] )
				? trailingslashit( $upload['baseurl'] ) . 'flags/' . basename( $name )
				: '';
		}

		if ( ! class_exists( \WPML_Flags::class ) ) {
			return '';
		}

		return \WPML_Flags::get_wpml_flags_url() . FlagFile::resolve( $name );
	}

	private function activeRows( $wpdb ) {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT /*+ MAX_EXECUTION_TIME(%d) */ code, english_name, is_custom, default_locale, country
				   FROM {$wpdb->prefix}icl_languages
				  WHERE active = %d
			   ORDER BY english_name ASC",
				$this->budget->hintMilliseconds(),
				1
			),
			ARRAY_A
		);

		return $this->readOrFailOpen( $wpdb, $rows );
	}

	private function flagRows( $wpdb ) {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT /*+ MAX_EXECUTION_TIME(%d) */ lang_code, flag, from_template
				   FROM {$wpdb->prefix}icl_flags",
				$this->budget->hintMilliseconds()
			),
			ARRAY_A
		);

		$out = [];
		foreach ( $this->readOrFailOpen( $wpdb, $rows ) as $row ) {
			$out[ (string) $row['lang_code'] ] = $row;
		}

		return $out;
	}

	private function localeMap( $wpdb ) {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT /*+ MAX_EXECUTION_TIME(%d) */ code, locale
				   FROM {$wpdb->prefix}icl_locale_map",
				$this->budget->hintMilliseconds()
			),
			ARRAY_A
		);

		$out = [];
		foreach ( $this->readOrFailOpen( $wpdb, $rows ) as $row ) {
			$out[ (string) $row['code'] ] = (string) $row['locale'];
		}

		return $out;
	}

	private function storedNames( $wpdb ) {
		$table = $wpdb->prefix . LanguageNames::TABLE;

		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			$this->reason = self::REASON_NO_LABELS_TABLE;

			return [];
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT /*+ MAX_EXECUTION_TIME(%d) */ language_code, display_language_code, name
				   FROM {$wpdb->prefix}icl_languages_translations",
				$this->budget->hintMilliseconds()
			),
			ARRAY_A
		);

		$out = [];
		foreach ( $this->readOrFailOpen( $wpdb, $rows ) as $row ) {
			$out[ (string) $row['language_code'] ][ (string) $row['display_language_code'] ] = (string) $row['name'];
		}

		return $out;
	}

	private function pairLocales( $wpdb, array $rows ) {
		$presets = [];
		foreach ( $rows as $row ) {
			if ( ! empty( $row['is_custom'] ) || '' === trim( (string) $row['country'] ) ) {
				continue;
			}
			$identity = $this->identity( (string) $row['code'] );
			if ( null === $identity ) {
				continue;
			}
			$presets[ (string) $identity['preset_code'] ] = true;
		}

		if ( ! $presets ) {
			return [];
		}

		$presetCodes = array_keys( $presets );
		$pairs       = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT /*+ MAX_EXECUTION_TIME(%d) */ preset_code, country_code, default_locale
				   FROM {$wpdb->prefix}icl_language_preset_countries
				  WHERE preset_code IN (" . implode( ', ', array_fill( 0, count( $presetCodes ), '%s' ) ) . ')',
				array_merge( [ $this->budget->hintMilliseconds() ], $presetCodes )
			),
			ARRAY_A
		);

		$out = [];
		foreach ( $this->readOrFailOpen( $wpdb, $pairs ) as $pair ) {
			$key         = $this->pairKey( (string) $pair['preset_code'], (string) $pair['country_code'] );
			$out[ $key ] = (string) $pair['default_locale'];
		}

		return $out;
	}

	private function pairKey( $presetCode, $country ) {
		return strtolower( $presetCode ) . '|' . strtoupper( $country );
	}

	private function readOrFailOpen( $wpdb, $rows ) {
		if ( ! is_array( $rows ) ) {
			$this->reason = self::REASON_READ_FAILED;

			return [];
		}

		if ( isset( $wpdb->last_error ) && '' !== (string) $wpdb->last_error ) {
			$this->reason = self::REASON_READ_FAILED;
		}

		return $rows;
	}

	private function payload( array $flags, array $labels, array $locales, array $codes ) {
		$cells = 0;
		foreach ( $labels as $label ) {
			$cells += (int) $label['cellCount'];
		}

		return [
			'groups'  => [
				self::GROUP_FLAGS   => $this->group( $flags ),
				self::GROUP_LABELS  => $this->group( $labels ) + [ 'cells' => $cells ],
				self::GROUP_LOCALES => $this->group( $locales ),
			],
			'total'   => count( $flags ) + count( $labels ) + count( $locales ),
			'codes'   => array_values( array_map( 'strval', array_keys( $codes ) ) ),
			'partial' => null !== $this->reason,
			'reason'  => $this->reason,
		];
	}

	private function group( array $languages ) {
		return [
			'count'     => count( $languages ),
			'listed'    => count( $languages ),
			'languages' => $languages,
		];
	}

	public static function forDialog( array $differences ) {
		foreach ( array_keys( $differences['groups'] ) as $group ) {
			$languages = array_slice(
				(array) $differences['groups'][ $group ]['languages'],
				0,
				self::MAX_LISTED_LANGUAGES
			);

			if ( self::GROUP_LABELS === $group ) {
				foreach ( $languages as $index => $language ) {
					$languages[ $index ]['cells'] = array_slice(
						(array) $language['cells'],
						0,
						self::MAX_LISTED_CELLS
					);
				}
			}

			$differences['groups'][ $group ]['languages'] = $languages;
			$differences['groups'][ $group ]['listed']    = count( $languages );
		}

		return $differences;
	}
}
