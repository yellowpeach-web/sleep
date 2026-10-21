<?php

namespace WPML\LanguageEditor;

use WPML\Core\Component\LanguageEditor\Domain\Bcp47;
use WPML\Core\Component\LanguageEditor\Domain\CustomLanguageFormat;
use WPML\LanguageEditor\Presets\RemoteCountryFlags;
use WPML\OperationRecord\UndoWindow;

use function WPML\Container\make;

class PageData {

	public static function structuralSaveEnabled() {
		return (bool) apply_filters( 'wpml_language_editor_structural_save', true );
	}

	public static function bootstrap() {
		self::ensureCatalogueSeeded();

		return [
			'ajaxUrl'                => admin_url( 'admin-ajax.php' ),
			'codeSpace'              => \WPML\Core\Component\LanguageEditor\Domain\CustomLanguageFormat::codeSpace(),
			'presets'                => self::presets(),
			'defaultPairs'           => self::defaultPairs(),
			'active'                 => self::active(),
			'labels'                 => self::labels(),
			'adminLanguage'          => self::adminLanguageCode(),
			'defaultCode'            => self::defaultCode(),
			'defaultLanguage'        => self::defaultLanguage(),
			'countryNames'           => self::countryNames(),
			'localeMap'              => self::localeMap(),
			'flagsUrl'               => self::flagsUrl(),
			'availableFlags'         => self::availableFlags(),
			'flagManifest'           => self::flagManifest(),
			'countries'              => self::countries(),
			'coverage'               => self::localMappingCoverage(),
			'ateBaseUrl'             => self::ateBaseUrl(),
			'ateEnabled'             => \WPML\TM\ATE\AutomaticTranslationCapabilities::isAvailable(),
			'shouldTranslateEverything' => \WPML\TM\ATE\AutomaticTranslationCapabilities::isAvailable()
				&& \WPML\Setup\Option::shouldTranslateEverything(),
			'structuralSaveEnabled'  => self::structuralSaveEnabled(),
			'simpleVariants'         => self::simpleVariants(),
			'skeletonDelayMs'        => (int) apply_filters( 'wpml_language_editor_skeleton_delay_ms', 0 ),
			'revivable'              => self::revivable(),
			'syncDelete'             => self::syncDeleteEnabled(),
			'trashDays'              => self::trashDays(),
			'urlFormat'              => self::urlFormat(),
		];
	}

	public static function urlFormatMode( $raw ) {
		if ( ! is_scalar( $raw ) || ! is_numeric( $raw ) ) {
			return \WPML\Core\LanguageNegotiation::DIRECTORY_STRING;
		}

		switch ( (int) $raw ) {
			case \WPML\Core\LanguageNegotiation::DOMAIN:
				return \WPML\Core\LanguageNegotiation::DOMAIN_STRING;
			case \WPML\Core\LanguageNegotiation::PARAMETER:
				return \WPML\Core\LanguageNegotiation::PARAMETER_STRING;
			default:
				return \WPML\Core\LanguageNegotiation::DIRECTORY_STRING;
		}
	}

	public static function urlFormat() {
		global $sitepress;

		if ( ! $sitepress ) {
			return [ 'mode' => \WPML\Core\LanguageNegotiation::DIRECTORY_STRING ];
		}

		$mode = self::urlFormatMode( $sitepress->get_setting( 'language_negotiation_type' ) );

		if ( \WPML\Core\LanguageNegotiation::DOMAIN_STRING !== $mode ) {
			return [
				'mode'                  => $mode,
				'domains'               => [],
				'suggestionBase'        => '',
				'homeHost'              => '',
				'homeScheme'            => '',
				'homePath'              => '',
				'domainValidationNonce' => '',
				'generalSettingsUrl'    => '',
			];
		}

		$home = (string) $sitepress->convert_url( $sitepress->get_wp_api()->get_home_url(), $sitepress->get_default_language() );

		$domains = [];
		foreach ( LanguageDomains::stored( $sitepress ) as $code => $domain ) {
			$host = LanguageDomains::shown( $domain );
			if ( '' !== $host ) {
				$domains[ (string) $code ] = $host;
			}
		}
		$homePort = wpml_parse_url( $home, PHP_URL_PORT );

		return [
			'mode'                  => $mode,
			'domains'               => $domains,
			'suggestionBase'        => \WPML_Lang_Domains_Box::suggested_base_host( $home ),
			'homeHost'              => (string) wpml_parse_url( $home, PHP_URL_HOST ) . ( $homePort ? ':' . $homePort : '' ),
			'homeScheme'            => (string) wpml_parse_url( $home, PHP_URL_SCHEME ),
			'homePath'              => (string) wpml_parse_url( $home, PHP_URL_PATH ),
			'domainValidationNonce' => wp_create_nonce( 'validate_language_domain' ),
			'generalSettingsUrl'    => admin_url( 'options-general.php' ),
		];
	}

	public static function trashDays( ?UndoWindow $window = null ) {
		return ( $window ? $window : new UndoWindow() )->days();
	}

	public static function syncDeleteEnabled() {
		return ( new \WPML\ContentDeletion\Settings() )->anyPostTypeDeletesAllLanguages();
	}

	public static function revivable() {
		global $wpdb;

		$table = $wpdb->prefix . 'icl_languages';
		$columns         = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" );
		$withDisplayCode = in_array( 'display_code', $columns, true );

		$rows = $wpdb->get_results(
			'SELECT code, english_name'
			. ( $withDisplayCode ? ', display_code' : '' )
			. " FROM {$wpdb->prefix}icl_languages
			 WHERE active <> 1 AND ( country IS NULL OR country = '' )"
		);
		if ( ! $rows ) {
			return [];
		}

		$codes  = array_map(
			function ( $row ) {
				return (string) $row->code;
			},
			(array) $rows
		);
		$totals = \WPML\Posts\TranslatedContentOfLanguages::totalsByLanguage( $codes );

		$revivable = [];
		foreach ( (array) $rows as $row ) {
			$code = (string) $row->code;
			if ( empty( $totals[ $code ] ) ) {
				continue;
			}
			$revivable[] = [
				'code'        => $code,
				'displayCode' => '' !== CustomLanguageFormat::usableDisplayCode( isset( $row->display_code ) ? $row->display_code : null )
					? (string) $row->display_code
					: $code,
				'englishName' => (string) $row->english_name,
				'total'       => (int) $totals[ $code ],
			];
		}

		return $revivable;
	}

	private static function localMappingCoverage() {
		$coverage = [];

		foreach ( (array) \WPML\Setup\Option::getLanguageMappings() as $mapping ) {
			$mapping = (object) $mapping;
			$source  = isset( $mapping->sourceCode ) ? (string) $mapping->sourceCode : '';
			$target  = isset( $mapping->targetCode ) ? (string) $mapping->targetCode : '';
			if ( '' === $source || '' === $target ) {
				continue;
			}

			$country = LanguageCodeResolution::country( str_replace( '_', '-', strtolower( $target ) ) );

			$coverage[ $source ] = [
				'canBeTranslatedAutomatically' => true,
				'mapping'                      => [
					'targetCode'    => $target,
					'targetCountry' => $country,
					'kind'          => MappingKind::of( $source, $target ),
				],
			];
		}

		return $coverage;
	}

	private static function ensureCatalogueSeeded() {
		global $wpdb;

		if ( ! function_exists( 'wpml_get_upgrade_schema' )
			|| ! class_exists( \WPML\Upgrade\Commands\SeedLanguageCatalogue::class ) ) {
			return;
		}

		if ( \WPML\Upgrade\Commands\SeedLanguageCatalogue::isCataloguePopulated( $wpdb ) ) {
			return;
		}

		try {
			( new \WPML\Upgrade\Commands\SeedLanguageCatalogue( [ wpml_get_upgrade_schema() ] ) )->run_admin();
		} catch ( \Throwable $e ) {
		}
	}

	private static function simpleVariants() {
		$default = array(
			'pt' => array( 'PT', 'BR' ),
			'fr' => array( 'FR', 'CA' ),
		);

		$feed = apply_filters( 'wpml_language_editor_simple_variants', $default );

		return is_array( $feed ) ? $feed : $default;
	}

	public static function presets() {
		global $wpdb;

		$table = $wpdb->prefix . 'icl_language_presets';
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return [];
		}

		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY english_name ASC" );

		$localized = self::localizedNameMap(
			array_map( function ( $r ) { return (string) $r->code; }, (array) $rows )
		);

		$identities = [];
		foreach ( (array) $rows as $r ) {
			$identities[ (string) $r->code ] = [
				'language' => isset( $r->language ) ? (string) $r->language : '',
				'script'   => isset( $r->script ) && '' !== (string) $r->script ? (string) $r->script : null,
			];
		}

		$countries = self::presetCountriesMap( $identities );

		$out = array_map(
			function ( $r ) use ( $localized, $countries ) {
				$code = (string) $r->code;
				$cc   = isset( $countries[ $code ] ) ? $countries[ $code ] : [ 'allowed' => [], 'default' => null, 'pairs' => [] ];

				$entry = [
					'code'             => $code,
					'englishName'      => (string) $r->english_name,
					'pairs'            => isset( $cc['pairs'] ) ? $cc['pairs'] : [],
					'localizedName'    => isset( $localized[ $code ] ) ? $localized[ $code ] : (string) $r->english_name,
					'language'         => isset( $r->language ) && '' !== (string) $r->language ? (string) $r->language : '',
					'script'           => isset( $r->script ) && $r->script !== '' ? (string) $r->script : null,
					'type'             => (string) $r->type,
					'countryMode'      => (string) $r->country_mode,
					'defaultCountry'   => $cc['default'],
					'allowedCountries' => $cc['allowed'],
					'languageFlag'     => isset( $r->language_flag ) && $r->language_flag !== '' ? (string) $r->language_flag : null,
					'wpCode'           => isset( $r->wp_code ) && $r->wp_code !== '' ? (string) $r->wp_code : null,
					'visibilityTier'   => isset( $r->visibility_tier ) && $r->visibility_tier !== '' ? (string) $r->visibility_tier : 'all',
				];

				if ( self::isUnvouched( $r ) ) {
					$entry['unvouched'] = true;
				}

				return $entry;
			},
			(array) $rows
		);

		self::sortByLocalizedName( $out );

		return $out;
	}

	public static function ateBaseUrl() {
		if ( ! class_exists( '\WPML_TM_ATE_AMS_Endpoints' ) ) {
			return '';
		}

		return (string) make( \WPML_TM_ATE_AMS_Endpoints::class )
			->get_base_url( \WPML_TM_ATE_AMS_Endpoints::SERVICE_ATE );
	}

	private static function presetCountriesMap( array $identities = [] ) {
		global $wpdb;

		$table = $wpdb->prefix . 'icl_language_preset_countries';
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return [];
		}

		$columns   = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" );
		$withOffer = in_array( 'offerable_flags', $columns, true );
		$withVouch = in_array( 'unvouched_at', $columns, true );
		$rows = $wpdb->get_results( 'SELECT preset_code, country_code, is_default, code, default_locale, flag' . ( $withOffer ? ', offerable_flags' : '' ) . ( $withVouch ? ', unvouched_at' : '' ) . " FROM {$table} ORDER BY preset_code ASC, sort_order ASC" );

		$map = [];
		foreach ( (array) $rows as $row ) {
			$pc = (string) $row->preset_code;
			$cc = strtoupper( (string) $row->country_code );
			if ( ! isset( $map[ $pc ] ) ) {
				$map[ $pc ] = [ 'allowed' => [], 'default' => null, 'pairs' => [] ];
			}
			if ( '' !== $cc ) {
				$map[ $pc ]['allowed'][] = $cc;
				if ( 1 === (int) $row->is_default ) {
					$map[ $pc ]['default'] = $cc;
				}
			}
			if ( isset( $row->code ) && null !== $row->code ) {
				$identity = isset( $identities[ $pc ] ) ? $identities[ $pc ] : [ 'language' => '', 'script' => null ];

				$map[ $pc ]['pairs'][ $cc ] = [
					'code'    => (string) $row->code,
					'locale'  => (string) $row->default_locale,
					'bcp47'   => Bcp47::compose( $identity['language'], $identity['script'], '' !== $cc ? $cc : null ),
					'flag'    => RemoteCountryFlags::resolveUrl( (string) $row->flag ),
					'country' => '' !== $cc ? $cc : null,
				];
				$granted = isset( $row->offerable_flags )
					? self::offerableFlagEntries( (string) $row->offerable_flags )
					: [];
				if ( $granted ) {
					$map[ $pc ]['pairs'][ $cc ]['offerableFlags'] = $granted;
				}
				if ( self::isUnvouched( $row ) ) {
					$map[ $pc ]['pairs'][ $cc ]['unvouched'] = true;
				}
			}
		}

		return $map;
	}

	private static function isUnvouched( $row ) {
		return isset( $row->unvouched_at ) && '' !== (string) $row->unvouched_at;
	}

	public static function countries() {
		global $wpdb;

		$table = $wpdb->prefix . 'icl_countries';
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return [];
		}

		$rows = $wpdb->get_results( "SELECT code, flag, english_name FROM {$wpdb->prefix}icl_countries" );

		$map = [];
		foreach ( (array) $rows as $row ) {
			$flag = isset( $row->flag ) && '' !== (string) $row->flag ? (string) $row->flag : null;

			$map[ strtoupper( (string) $row->code ) ] = [
				'flag' => null !== $flag ? RemoteCountryFlags::resolveUrl( $flag ) : null,
				'name' => (string) $row->english_name,
			];
		}

		return $map;
	}

	public static function defaultPairs() {
		$out = self::cataloguePickerDefaults();
		if ( null === $out || ! self::catalogueHasSynced() ) {
			$out = self::shippedPickerDefaults();
		}

		return (array) apply_filters( 'wpml_language_editor_default_pairs', $out );
	}

	private static function catalogueHasSynced() {
		$store = new Presets\CatalogueVersionStore();

		return null !== $store->getVersion( Presets\CatalogueVersionStore::SECTION_LANGUAGES );
	}

	private static function cataloguePickerDefaults() {
		global $wpdb;

		$table = $wpdb->prefix . 'icl_language_preset_countries';
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return null;
		}

		$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$wpdb->prefix}icl_language_preset_countries`" );
		if ( ! in_array( 'picker_default', $columns, true ) ) {
			return null;
		}

		$rows = (array) $wpdb->get_results( "SELECT preset_code, country_code FROM {$wpdb->prefix}icl_language_preset_countries WHERE picker_default = 1 ORDER BY preset_code ASC, sort_order ASC, country_code ASC" );

		$out = [];
		foreach ( $rows as $row ) {
			if ( ! isset( $row->preset_code ) ) {
				continue;
			}
			$country = isset( $row->country_code ) && '' !== (string) $row->country_code
				? strtoupper( (string) $row->country_code )
				: null;
			$out[]   = [
				'code'    => (string) $row->preset_code,
				'country' => $country,
			];
		}

		return $out;
	}

	private static function shippedPickerDefaults() {
		$out = [];
		foreach ( Presets\LanguagePresetsDefaultPairs::data() as $pair ) {
			if ( ! is_array( $pair ) || ! isset( $pair[0] ) ) {
				continue;
			}
			$country = isset( $pair[1] ) && '' !== (string) $pair[1] ? strtoupper( (string) $pair[1] ) : null;
			$out[]   = [
				'code'    => (string) $pair[0],
				'country' => $country,
			];
		}

		return $out;
	}

	public static function active() {
		global $wpdb;

		$default = self::defaultCode();
		$hidden  = self::hiddenCodes();

		$table = $wpdb->prefix . 'icl_languages';
		$columns  = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" );
		$withTag  = in_array( 'bcp_47', $columns, true );
		$withRtl  = in_array( 'is_rtl', $columns, true );

		$rows = $wpdb->get_results(
			'SELECT code, english_name, default_locale, tag, country, display_code, encode_url, is_custom'
			 . ( $withTag ? ', bcp_47' : '' )
			 . ( $withRtl ? ', is_rtl' : '' )
			 . " FROM {$wpdb->prefix}icl_languages
			 WHERE active = 1
			 ORDER BY english_name ASC"
		);

		$rows = (array) $rows;
		if ( $rows && '' !== $default && ! in_array( $default, array_map( function ( $r ) { return (string) $r->code; }, $rows ), true ) ) {
			$own = self::languageRow( $default );
			if ( null !== $own ) {
				$rows[] = $own;
			}
		}

		$adminLang = self::adminLanguageCode();
		$composed  = '' !== $adminLang
			? self::composedNameMatrix(
				array_map( function ( $r ) { return (string) $r->code; }, (array) $rows ),
				[ $adminLang ]
			)
			: [];

		$flags = self::legacyFlags();

		$paused = \WPML\LanguageEditor\TranslationPause::pausedCodes();

		$out = array_map(
			function ( $r ) use ( $default, $hidden, $composed, $adminLang, $flags, $paused, $withRtl ) {
				$code        = (string) $r->code;
				$displayCode = '' !== CustomLanguageFormat::usableDisplayCode( isset( $r->display_code ) ? $r->display_code : null ) ? (string) $r->display_code : $code;
				$country     = isset( $r->country ) && $r->country !== '' ? (string) $r->country : null;
				$identity    = LanguageCodeResolution::publishedIdentity( $code );
				$bcp47       = isset( $r->bcp_47 ) && '' !== (string) $r->bcp_47 ? (string) $r->bcp_47 : null;

				$row = [
					'code'        => $code,
					'displayCode' => $displayCode,
					'englishName' => (string) $r->english_name,
					'localizedName' => isset( $composed[ $code ][ $adminLang ] ) && '' !== $composed[ $code ][ $adminLang ]
						? $composed[ $code ][ $adminLang ]
						: (string) $r->english_name,
					'country'     => $country,
					'language'    => $identity['language'],
					'script'      => $identity['script'],
					'locale'      => (string) ( $r->default_locale ?? '' ),
					'hreflang'    => isset( $r->tag ) && $r->tag !== '' ? (string) $r->tag : $displayCode,
					'encodeUrl'   => (bool) $r->encode_url,
					'isRtl'       => $withRtl && isset( $r->is_rtl ) && null !== $r->is_rtl ? (bool) (int) $r->is_rtl : null,
					'isCustom'    => isset( $r->is_custom ) ? (bool) $r->is_custom : false,
					'customIdentity' => LanguageCodeResolution::isCustomIdentity( $code ),
					'flag'        => isset( $flags[ $code ] ) ? $flags[ $code ] : '',
					'hidden'      => in_array( $code, $hidden, true ),
					'isDefault'   => $code === $default,
					'translationPaused' => in_array( $code, $paused, true ),
				];

				if ( null !== $bcp47 ) {
					$row['bcp47'] = $bcp47;
				}

				return $row;
			},
			(array) $rows
		);

		self::sortByLocalizedName( $out );

		return $out;
	}

	public static function labels() {
		global $wpdb;

		$codes = $wpdb->get_col(
			"SELECT code FROM {$wpdb->prefix}icl_languages WHERE active = 1"
		);
		$codes = array_map( 'strval', (array) $codes );
		if ( empty( $codes ) ) {
			return [];
		}

		return self::composedNameMatrix( $codes, $codes );
	}

	public static function composedLabelsFor( $code, array $displays ) {
		$code = (string) $code;
		$displays = array_values( array_filter( array_map( 'strval', $displays ), 'strlen' ) );
		if ( '' === $code || empty( $displays ) ) {
			return [];
		}

		$matrix = self::composedNameMatrix( [ $code ], $displays );

		return isset( $matrix[ $code ] ) ? $matrix[ $code ] : [];
	}

	private static function composedNameMatrix( array $sources, array $displays ) {
		$service = new Labels();
		$pairs   = self::variantPairInfo();
		$saved   = self::savedLabelOverrides( $sources );

		$displayOf = [];
		foreach ( $displays as $display ) {
			$displayOf[ $display ] = isset( $pairs[ $display ] ) && '' !== $pairs[ $display ]['preset']
				? $pairs[ $display ]['preset']
				: $display;
		}
		$displayLangs  = array_values( array_unique( array_merge( $displays, array_values( $displayOf ) ) ) );
		$countryByLang = self::countryNamesByLanguage( $displayLangs );

		$rowFacts        = self::rowFacts( $sources );
		$englishNames    = array_map( function ( $facts ) { return $facts['english_name']; }, $rowFacts );
		$storedCountries = array_map( function ( $facts ) { return $facts['country']; }, $rowFacts );

		$defaultByPair = [];
		foreach ( $pairs as $pair ) {
			$defaultByPair[ $pair['preset'] ][ $pair['country'] ] = $pair['is_default'];
		}

		$multiBases = self::multiVariantActiveBases();

		$matrix = [];
		foreach ( $sources as $source ) {
			$info      = isset( $pairs[ $source ] ) && ! LanguageCodeResolution::isCustomIdentity( $source )
				? $pairs[ $source ]
				: null;
			$base = $info && '' !== $info['preset'] ? $info['preset'] : $source;

			$stored    = isset( $storedCountries[ $source ] ) ? $storedCountries[ $source ] : '';
			$country   = $info ? ( '' !== $stored ? $stored : $info['country'] ) : '';
			$isDefault = $info && isset( $defaultByPair[ $base ][ $country ] ) ? $defaultByPair[ $base ][ $country ] : false;
			$fallback  = isset( $englishNames[ $source ] ) ? $englishNames[ $source ] : '';

			$hasActiveSibling = isset( $multiBases[ $base ] );
			$composesCountry  = '' !== $country && ( ! $isDefault || $hasActiveSibling );
			$lookup = $composesCountry
				? $base
				: ( ( ( '' === $country || $isDefault ) && $source !== $base ) ? $source : $base );

			$langByDisplay = $composesCountry && $lookup === $base
				? $service->getDerived( $lookup, $displayLangs )
				: $service->get( $lookup, $displayLangs, $fallback );

			$row = [];
			foreach ( $displays as $display ) {
				$dl = $displayOf[ $display ];

				$ownExact = isset( $saved[ $source ][ $display ] ) ? (string) $saved[ $source ][ $display ] : '';
				$ownBase  = isset( $saved[ $source ][ $dl ] ) ? (string) $saved[ $source ][ $dl ] : '';
				$own      = ( '' !== $ownExact && $ownExact !== $fallback )
					? $ownExact
					: ( ( '' !== $ownBase && $ownBase !== $fallback ) ? $ownBase : '' );
				if ( '' !== $own ) {
					$row[ $display ] = $own;
					continue;
				}

				$name = ( $lookup === $source && isset( $langByDisplay[ $display ] ) && '' !== $langByDisplay[ $display ] )
					? $langByDisplay[ $display ]
					: ( isset( $langByDisplay[ $dl ] ) && '' !== $langByDisplay[ $dl ]
						? $langByDisplay[ $dl ]
						: ( '' !== $fallback ? $fallback : $base ) );

				if ( ! $composesCountry && $info && '' !== $fallback && $name === $fallback ) {
					$catalogueName = LanguageNames::nameFor( $source, $display );
					if ( null !== $catalogueName && '' !== $catalogueName ) {
						$name = $catalogueName;
					}
				}

				if ( $composesCountry ) {
					$countryName = isset( $countryByLang[ $dl ][ $country ] ) ? $countryByLang[ $dl ][ $country ] : '';
					if ( '' !== $countryName ) {
						$name = sprintf( '%1$s (%2$s)', $name, $countryName );
					}
				}

				$row[ $display ] = $name;
			}

			$matrix[ $source ] = $row;
		}

		return $matrix;
	}

	private static function rowFacts( array $codes ) {
		$codes = array_values( array_filter( array_map( 'strval', $codes ), 'strlen' ) );
		if ( empty( $codes ) ) {
			return [];
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $codes ), '%s' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT code, english_name, country FROM {$wpdb->prefix}icl_languages WHERE code IN ($placeholders)",
				$codes
			)
		);

		$out = [];
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row->code ] = [
				'english_name' => (string) $row->english_name,
				'country'      => strtoupper( (string) ( isset( $row->country ) ? $row->country : '' ) ),
			];
		}

		return $out;
	}

	private static function savedLabelOverrides( array $sources ) {
		if ( empty( $sources ) ) {
			return [];
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $sources ), '%s' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT language_code, display_language_code, name
				 FROM {$wpdb->prefix}icl_languages_translations
				 WHERE language_code IN ($placeholders)",
				$sources
			),
			ARRAY_A
		);

		$out = [];
		foreach ( (array) $rows as $r ) {
			$out[ (string) $r['language_code'] ][ (string) $r['display_language_code'] ] = (string) $r['name'];
		}

		return $out;
	}

	private static function variantPairInfo() {
		global $wpdb;

		$table = $wpdb->prefix . 'icl_language_preset_countries';
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return [];
		}

		$rows = $wpdb->get_results( "SELECT code, preset_code, country_code, is_default FROM {$table}" );

		$map = [];
		foreach ( (array) $rows as $row ) {
			$map[ (string) $row->code ] = [
				'preset'     => (string) $row->preset_code,
				'country'    => strtoupper( (string) $row->country_code ),
				'is_default' => (bool) (int) $row->is_default,
			];
		}

		return $map;
	}

	private static function multiVariantActiveBases(): array {
		global $wpdb;
		$codes  = (array) $wpdb->get_col( "SELECT code FROM {$wpdb->prefix}icl_languages WHERE active = 1" );
		$pairs  = self::variantPairInfo();
		$counts = [];
		foreach ( $codes as $code ) {
			$code = (string) $code;
			if ( LanguageCodeResolution::isCustomIdentity( $code ) ) {
				continue;
			}
			$base           = isset( $pairs[ $code ] ) && '' !== $pairs[ $code ]['preset'] ? $pairs[ $code ]['preset'] : $code;
			$counts[ $base ] = ( isset( $counts[ $base ] ) ? $counts[ $base ] : 0 ) + 1;
		}

		return array_filter( $counts, function ( $n ) {
			return $n > 1;
		} );
	}

	private static function countryNamesByLanguage( array $langs ) {
		$english = [];
		foreach ( self::countries() as $code => $country ) {
			$english[ (string) $code ] = (string) $country['name'];
		}

		global $wpdb;
		$byLang = [];
		$table  = $wpdb->prefix . 'icl_countries_translations';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			$rows = $wpdb->get_results( "SELECT country_code, display_language_code, name FROM {$table}" );
			foreach ( (array) $rows as $row ) {
				$byLang[ (string) $row->display_language_code ][ strtoupper( (string) $row->country_code ) ] = (string) $row->name;
			}
		}

		$out = [];
		foreach ( $langs as $lang ) {
			$out[ $lang ] = isset( $byLang[ $lang ] ) ? array_merge( $english, $byLang[ $lang ] ) : $english;
		}

		return $out;
	}

	private static function legacyFlags() {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT lang_code, flag, from_template FROM {$wpdb->prefix}icl_flags WHERE flag <> ''"
		);

		$upload      = wp_upload_dir();
		$customBase  = ! empty( $upload['baseurl'] ) ? trailingslashit( $upload['baseurl'] ) . 'flags/' : '';

		$map = [];
		foreach ( (array) $rows as $row ) {
			$map[ (string) $row->lang_code ] = $row->from_template && '' !== $customBase
				? $customBase . (string) $row->flag
				: (string) $row->flag;
		}

		return $map;
	}

	private static function offerableFlagEntries( $stored ) {
		$stored = trim( (string) $stored );
		if ( '' === $stored ) {
			return [];
		}

		static $flagToCountry = null;
		if ( null === $flagToCountry ) {
			$flagToCountry = [];
			foreach ( self::countries() as $code => $country ) {
				$raw  = (string) ( $country['flag'] ?? '' );
				$file = Flags\FlagFile::normalize( $raw );
				if ( '' === $file ) {
					$file = strtolower( basename( (string) wp_parse_url( $raw, PHP_URL_PATH ) ) );
				}
				if ( '' !== $file && ! isset( $flagToCountry[ $file ] ) ) {
					$flagToCountry[ $file ] = (string) $code;
				}
			}
		}
		$names    = self::countryNames();
		$flagsUrl = self::flagsUrl();

		$entries = [];
		foreach ( array_map( 'trim', explode( ',', $stored ) ) as $file ) {
			$file = strtolower( $file );
			if ( '' === $file ) {
				continue;
			}
			if ( Flags\FlagFile::isShippedName( $file ) && ! Flags\FlagFile::exists( $file ) ) {
				continue;
			}
			$cc    = isset( $flagToCountry[ $file ] ) ? $flagToCountry[ $file ] : null;
			$label = null !== $cc && isset( $names[ $cc ] )
				/* translators: Screen reader name of a flag image. %s: the name of the country or language it stands for, as in "Flag for Spain". */
				? sprintf( __( 'Flag for %s', 'sitepress' ), $names[ $cc ] )
				: $file;
			$url = Presets\RemoteCountryFlags::resolveUrl( $file );
			if ( false === strpos( $url, '/' ) || Flags\FlagFile::isShippedName( $url ) ) {
				$url = $flagsUrl . $url;
			}
			$entries[] = [
				'file'  => $file,
				'label' => $label,
				'url'   => $url,
			];
		}

		return $entries;
	}

	public static function countryNames() {
		$localized = self::localizedCountryNameMap();

		$names = [];
		foreach ( self::countries() as $code => $country ) {
			$code = (string) $code;
			if ( isset( $localized[ $code ] ) ) {
				$names[ $code ] = $localized[ $code ];
			} elseif ( '' !== (string) $country['name'] ) {
				$names[ $code ] = (string) $country['name'];
			}
		}

		return (array) apply_filters( 'wpml_language_editor_country_names', $names );
	}

	private static function localizedCountryNameMap() {
		$adminLang = self::adminLanguageCode();
		if ( '' === $adminLang ) {
			return [];
		}

		global $wpdb;
		$table = $wpdb->prefix . 'icl_countries_translations';
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return [];
		}

		$baseLang = \WPML\LanguageEditor\LanguageNames::baseDisplayCode( $adminLang );
		$langs    = array_values( array_unique( array_filter( [ $adminLang, $baseLang ], 'strlen' ) ) );

		$placeholders = implode( ',', array_fill( 0, count( $langs ), '%s' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT country_code, display_language_code, name FROM {$table} WHERE display_language_code IN ($placeholders)",
				$langs
			),
			ARRAY_A
		);

		$byLang = [];
		foreach ( (array) $rows as $row ) {
			$byLang[ (string) $row['display_language_code'] ][ strtoupper( (string) $row['country_code'] ) ] = (string) $row['name'];
		}
		$map = '' !== $baseLang && isset( $byLang[ $baseLang ] ) ? $byLang[ $baseLang ] : [];
		if ( isset( $byLang[ $adminLang ] ) ) {
			$map = array_merge( $map, $byLang[ $adminLang ] );
		}

		return array_filter( $map, function ( $name ) { return '' !== trim( (string) $name ); } );
	}

	private static function localeMap() {
		return function_exists( 'icl_get_languages_locales' ) ? icl_get_languages_locales() : [];
	}

	public static function defaultCode() {
		global $sitepress;

		$code = $sitepress ? (string) $sitepress->get_default_language() : '';

		if ( '' === $code ) {
			$seed = self::defaultLanguage();
			$code = isset( $seed['code'] ) ? (string) $seed['code'] : '';
		}

		return self::drawableDefaultCode( $code );
	}

	private static function drawableDefaultCode( $stored ) {
		global $wpdb;

		if ( '' === $stored || ! $wpdb ) {
			return $stored;
		}

		$active = (array) $wpdb->get_results(
			"SELECT code, country FROM {$wpdb->prefix}icl_languages WHERE active = 1 ORDER BY english_name ASC"
		);
		if ( ! $active ) {
			return $stored;
		}

		foreach ( $active as $row ) {
			if ( (string) $row->code === $stored ) {
				return $stored;
			}
		}
		foreach ( $active as $row ) {
			if ( strtolower( (string) $row->code ) === strtolower( $stored ) ) {
				return (string) $row->code;
			}
		}

		$own = self::languageRow( $stored );
		if ( null !== $own ) {
			$identity = LanguageCodeResolution::publishedIdentity( (string) $own->code );
			if ( '' !== $identity['language'] ) {
				$country = strtoupper( (string) ( isset( $own->country ) ? $own->country : '' ) );
				foreach ( array( $country, '' ) as $wanted ) {
					foreach ( $active as $row ) {
						$candidate = LanguageCodeResolution::publishedIdentity( (string) $row->code );
						if ( $candidate['language'] === $identity['language']
							&& $candidate['script'] === $identity['script']
							&& strtoupper( (string) ( isset( $row->country ) ? $row->country : '' ) ) === $wanted ) {
							return (string) $row->code;
						}
					}
				}
			}

			return (string) $own->code;
		}

		return (string) $active[0]->code;
	}

	private static function languageRow( $code ) {
		global $wpdb;

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_languages WHERE code = %s", $code )
		);

		foreach ( $rows as $row ) {
			if ( is_object( $row ) && isset( $row->code ) && strtolower( (string) $row->code ) === strtolower( (string) $code ) ) {
				return $row;
			}
		}

		return null;
	}

	public static function defaultLanguage() {
		static $cache = false;
		if ( false !== $cache ) {
			return $cache;
		}

		$locale    = self::wpLocale();
		$suggested = self::suggestedPreset( $locale );

		if ( null === $suggested ) {
			error_log( sprintf( '[WPML] Language editor: WP locale "%s" matched no offerable language preset; falling back to en_US for the default language.', $locale ) );

			$cache = self::fallbackDefaultLanguage();
			return $cache;
		}

		$preset  = $suggested['preset'];
		$country = $suggested['country'];

		$pairKey = null !== $country ? strtoupper( (string) $country ) : '';
		$pair    = isset( $preset['pairs'][ $pairKey ] ) ? $preset['pairs'][ $pairKey ] : null;
		$code    = $pair && isset( $pair['code'] ) && '' !== (string) $pair['code']
			? (string) $pair['code']
			: (string) $preset['code'];

		$cache = [
			'code'          => $code,
			'displayCode'   => (string) $preset['code'],
			'englishName'   => (string) $preset['englishName'],
			'localizedName' => isset( $preset['localizedName'] ) && '' !== (string) $preset['localizedName']
				? (string) $preset['localizedName']
				: (string) $preset['englishName'],
			'type'          => (string) $preset['type'],
			'language'      => isset( $preset['language'] ) ? (string) $preset['language'] : '',
			'script'        => isset( $preset['script'] ) && '' !== (string) $preset['script'] ? (string) $preset['script'] : null,
			'country'       => $country,
			'locale'        => $locale,
			'hreflang'      => $code,
			'encodeUrl'     => false,
			'flag'          => '',
			'hidden'        => false,
			'isDefault'     => true,
		];

		return $cache;
	}

	private static function suggestedPreset( $locale ) {
		$resolved = self::presetCountryByLocale( $locale );
		if ( $resolved ) {
			$preset  = self::presetByCode( $resolved['code'] );
			$country = $preset ? self::offerableCountry( $preset, $resolved['country'] ) : false;
			if ( false !== $country ) {
				return [ 'preset' => $preset, 'country' => $country ];
			}
		}

		$preset = self::localeToPreset( $locale );
		if ( ! $preset ) {
			return null;
		}

		$country = self::countryForLocale( $locale, (string) $preset['code'] );
		if ( null === $country ) {
			$country = isset( $preset['defaultCountry'] ) && $preset['defaultCountry'] !== ''
				? (string) $preset['defaultCountry']
				: null;
		}

		$country = self::offerableCountry( $preset, $country );

		return false === $country ? null : [ 'preset' => $preset, 'country' => $country ];
	}

	private static function offerableCountry( array $preset, $country ) {
		if ( ! empty( $preset['unvouched'] ) ) {
			return false;
		}

		$pairs = isset( $preset['pairs'] ) && is_array( $preset['pairs'] ) ? $preset['pairs'] : [];
		$key   = function ( $candidate ) {
			return null !== $candidate ? strtoupper( (string) $candidate ) : '';
		};

		$asked = $key( $country );
		if ( ! isset( $pairs[ $asked ] ) || empty( $pairs[ $asked ]['unvouched'] ) ) {
			return $country;
		}

		$candidates = [];
		if ( isset( $preset['defaultCountry'] ) && '' !== (string) $preset['defaultCountry'] ) {
			$candidates[] = (string) $preset['defaultCountry'];
		}
		foreach ( (array) ( isset( $preset['allowedCountries'] ) ? $preset['allowedCountries'] : [] ) as $allowed ) {
			$candidates[] = (string) $allowed;
		}
		if ( ! isset( $preset['countryMode'] ) || 'required' !== (string) $preset['countryMode'] ) {
			$candidates[] = null;
		}

		foreach ( $candidates as $candidate ) {
			$candidateKey = $key( $candidate );
			if ( $candidateKey !== $asked && isset( $pairs[ $candidateKey ] ) && empty( $pairs[ $candidateKey ]['unvouched'] ) ) {
				return $candidate;
			}
		}

		return false;
	}

	private static function fallbackDefaultLanguage() {
		return [
			'code'          => 'en',
			'displayCode'   => 'en',
			'englishName'   => 'English',
			'localizedName' => 'English',
			'type'          => 'national',
			'language'      => 'en',
			'script'        => null,
			'country'       => 'US',
			'locale'        => 'en_US',
			'hreflang'      => 'en',
			'encodeUrl'     => false,
			'flag'          => '',
			'hidden'        => false,
			'isDefault'     => true,
		];
	}

	public static function wpLocale() {
		return function_exists( 'get_locale' ) ? (string) get_locale() : '';
	}

	public static function localeToPreset( $locale ) {
		$locale = trim( (string) $locale );
		if ( '' === $locale ) {
			return null;
		}

		$presets = self::presets();

		foreach ( $presets as $preset ) {
			if ( isset( $preset['wpCode'] ) && 0 === strcasecmp( (string) $preset['wpCode'], $locale ) ) {
				return $preset;
			}
		}

		$lang = strtolower( explode( '_', $locale )[0] );
		foreach ( $presets as $preset ) {
			if ( 0 === strcasecmp( (string) $preset['code'], $lang ) ) {
				return $preset;
			}
		}

		$aliases = apply_filters( 'wpml_wp_locale_code_aliases', [ 'arg' => 'an' ] );
		if ( is_array( $aliases ) && isset( $aliases[ $lang ] ) ) {
			$aliasCode = strtolower( (string) $aliases[ $lang ] );
			foreach ( $presets as $preset ) {
				if ( 0 === strcasecmp( (string) $preset['code'], $aliasCode ) ) {
					return $preset;
				}
			}
		}

		return null;
	}

	private static function countryForLocale( $locale, $presetCode ) {
		$locale = trim( (string) $locale );
		if ( '' === $locale || '' === $presetCode ) {
			return null;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'icl_language_preset_countries';

		$country = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT country_code FROM `{$table}` WHERE preset_code = %s AND default_locale = %s AND country_code <> '' LIMIT 1",
				$presetCode,
				$locale
			)
		);

		return is_string( $country ) && '' !== $country ? strtoupper( $country ) : null;
	}

	private static function presetCountryByLocale( $locale ) {
		$locale = trim( (string) $locale );
		if ( '' === $locale ) {
			return null;
		}

		global $wpdb;
		$table   = $wpdb->prefix . 'icl_language_preset_countries';
		$presets = $wpdb->prefix . 'icl_language_presets';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT c.preset_code, c.country_code FROM `{$table}` c
				 LEFT JOIN `{$presets}` p ON p.code = c.preset_code
				 WHERE c.default_locale = %s
				 ORDER BY c.is_default DESC,
				          CASE p.visibility_tier WHEN 'default' THEN 0 WHEN 'all' THEN 1 ELSE 2 END ASC,
				          ( c.country_code = '' ) DESC,
				          c.id ASC
				 LIMIT 1",
				$locale
			)
		);

		if ( ! $row || null === $row->preset_code || '' === $row->preset_code ) {
			return null;
		}

		return [
			'code'    => (string) $row->preset_code,
			'country' => is_string( $row->country_code ) && '' !== $row->country_code ? strtoupper( $row->country_code ) : null,
		];
	}

	private static function presetByCode( $code ) {
		foreach ( self::presets() as $preset ) {
			if ( 0 === strcasecmp( (string) $preset['code'], (string) $code ) ) {
				return $preset;
			}
		}

		return null;
	}

	public static function flagsUrl() {
		if ( class_exists( \WPML_Flags::class ) ) {
			return (string) \WPML_Flags::get_wpml_flags_url();
		}

		return defined( 'ICL_PLUGIN_URL' ) ? ICL_PLUGIN_URL . '/res/flags/' : '';
	}

	public static function availableFlags() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		$dir   = defined( 'ICL_PLUGIN_PATH' ) ? ICL_PLUGIN_PATH . '/res/flags/' : '';
		$names = [];
		if ( '' !== $dir ) {
			foreach ( [ '', 'country/', 'language/' ] as $sub ) {
				foreach ( (array) glob( $dir . $sub . '*.svg' ) as $file ) {
					$names[] = $sub . basename( (string) $file );
				}
			}
		}

		$cache = array_values( (array) apply_filters( 'wpml_language_editor_available_flags', $names ) );

		return $cache;
	}

	public static function flagManifest() {
		$names   = self::countryNames();
		$grouped = [];

		foreach ( Flags\FlagManifest::instance()->byKind() as $kind => $entries ) {
			$grouped[ $kind ] = [];
			foreach ( $entries as $entry ) {
				$code = isset( $entry['code'] ) ? strtoupper( (string) $entry['code'] ) : '';
				$name = isset( $entry['name'] ) ? (string) $entry['name'] : '';

				$grouped[ $kind ][] = [
					'file' => (string) $entry['file'],
					'name' => '' !== $code && isset( $names[ $code ] ) ? $names[ $code ] : $name,
					'code' => $code,
				];
			}
		}

		return $grouped;
	}

	private static function adminLanguageCode() {
		global $sitepress;

		$code = self::catalogueLanguageForLocale( get_user_locale() );

		return (string) apply_filters( 'wpml_language_editor_admin_language', $code );
	}

	private static function catalogueLanguageForLocale( $locale ) {
		global $sitepress;

		$locale = (string) $locale;
		if ( '' === $locale || ! $sitepress || ! method_exists( $sitepress, 'get_languages' ) ) {
			return '';
		}

		$rows = (array) $sitepress->get_languages( 'en', false );

		$match = '';
		foreach ( $rows as $row ) {
			if ( ! isset( $row['default_locale'], $row['code'] ) || (string) $row['default_locale'] !== $locale ) {
				continue;
			}
			if ( ! empty( $row['active'] ) ) {
				return (string) $row['code'];
			}
			if ( '' === $match ) {
				$match = (string) $row['code'];
			}
		}
		if ( '' !== $match ) {
			return $match;
		}

		$language = strtolower( (string) strtok( $locale, '_' ) );

		return '' !== $language && isset( $rows[ $language ] ) ? $language : '';
	}

	private static function localizedNameMap( array $codes ) {
		$adminLang = self::adminLanguageCode();
		if ( '' === $adminLang ) {
			return [];
		}

		$baseLang = LanguageNames::baseDisplayCode( $adminLang );
		$displays = array_values( array_unique( array_filter( [ $adminLang, $baseLang ], 'strlen' ) ) );

		global $wpdb;
		$saved        = [];
		$placeholders = implode( ',', array_fill( 0, count( $displays ), '%s' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT language_code, display_language_code, name
				 FROM {$wpdb->prefix}icl_languages_translations
				 WHERE display_language_code IN ($placeholders)",
				$displays
			),
			ARRAY_A
		);
		foreach ( (array) $rows as $row ) {
			if ( '' !== trim( (string) $row['name'] ) ) {
				$saved[ (string) $row['display_language_code'] ][ (string) $row['language_code'] ] = (string) $row['name'];
			}
		}

		$ownNames  = isset( $saved[ $adminLang ] ) ? $saved[ $adminLang ] : [];
		$baseNames = ( '' !== $baseLang && isset( $saved[ $baseLang ] ) ) ? $saved[ $baseLang ] : [];
		$builtIn   = self::builtInNamesInLanguage( $adminLang );

		$map = [];
		foreach ( $codes as $code ) {
			$code      = (string) $code;
			$catalogue = LanguageNames::nameFor( $code, $adminLang );

			if ( isset( $ownNames[ $code ] ) ) {
				$map[ $code ] = $ownNames[ $code ];
			} elseif ( isset( $baseNames[ $code ] ) ) {
				$map[ $code ] = $baseNames[ $code ];
			} elseif ( null !== $catalogue ) {
				$map[ $code ] = $catalogue;
			} elseif ( isset( $builtIn[ $code ] ) ) {
				$map[ $code ] = $builtIn[ $code ];
			}
		}

		return $map;
	}

	private static function builtInNamesInLanguage( $displayLang ) {
		if ( ! function_exists( 'icl_get_languages_names' ) || ! function_exists( 'icl_get_languages_codes' ) ) {
			return [];
		}

		$names = icl_get_languages_names();
		$codes = icl_get_languages_codes();

		$displayEnglishName = array_search( $displayLang, $codes, true );
		if ( false === $displayEnglishName ) {
			return [];
		}

		$out = [];
		foreach ( $names as $sourceEnglishName => $data ) {
			if ( ! isset( $codes[ $sourceEnglishName ], $data['tr'] ) ) {
				continue;
			}
			$sourceCode = $codes[ $sourceEnglishName ];
			foreach ( $data['tr'] as $displayName => $localized ) {
				if ( 0 === strpos( (string) $displayName, 'Norwegian Bokm' ) ) {
					$displayName = 'Norwegian Bokmål';
				}
				if ( $displayName === $displayEnglishName && '' !== trim( (string) $localized ) ) {
					$out[ $sourceCode ] = (string) $localized;
					break;
				}
			}
		}

		return $out;
	}

	private static function sortByLocalizedName( array &$rows ) {
		$collator = null;
		if ( class_exists( '\Collator' ) ) {
			$adminLang = self::adminLanguageCode();
			$collator  = collator_create( '' !== $adminLang ? str_replace( '-', '_', $adminLang ) : 'en' );
		}

		usort(
			$rows,
			function ( $a, $b ) use ( $collator ) {
				$an = (string) ( $a['localizedName'] ?? $a['englishName'] ?? '' );
				$bn = (string) ( $b['localizedName'] ?? $b['englishName'] ?? '' );

				if ( $collator ) {
					return (int) collator_compare( $collator, $an, $bn );
				}

				return strcasecmp( $an, $bn );
			}
		);
	}

	private static function hiddenCodes() {
		global $sitepress;

		if ( ! $sitepress ) {
			return [];
		}
		$hidden = $sitepress->get_setting( 'hidden_languages' );

		return is_array( $hidden ) ? array_map( 'strval', $hidden ) : [];
	}
}
