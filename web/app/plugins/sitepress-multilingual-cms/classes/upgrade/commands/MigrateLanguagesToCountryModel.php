<?php

namespace WPML\Upgrade\Commands;

use WPML\LanguageEditor\Presets\PresetsSeeder;
use function WPML\Container\make;

class MigrateLanguagesToCountryModel implements \IWPML_Upgrade_Command, \IWPML_Pre_Setup_Upgrade_Command {

	protected $schema;

	protected $result = false;

	private static $known_countries = null;

	const OPTION_ATE_MAPPING_DEFERRED = 'wpml_country_migration_ate_mapping_deferred';

	const OPTION_ATE_MAPPING_REAPPLY_NEXT_ATTEMPT = 'wpml_ate_mapping_reapply_next_attempt';

	const ATE_MAPPING_REAPPLY_BACKOFF = 300;

	const OPTION_TYPE_UNDETERMINED = 'wpml_language_type_undetermined';

	const PAIR_DECODER = [
		'pt-br'   => [ 'pt', 'BR' ],
		'pt-pt'   => [ 'pt', 'PT' ],
		'zh-hans' => [ 'zh-hans', 'CN' ],
		'zh-hant' => [ 'zh-hant', 'TW' ],
	];

	const DEFAULT_SCRIPT = [
		'az' => 'az-latn',
		'bs' => 'bs-latn',
		'kk' => 'kk-kk',
		'ku' => 'ku-arab',
		'mn' => 'mn-cyrl',
		'ms' => 'ms-latn',
		'pa' => 'pa-guru',
		'sr' => 'sr-latn',
		'uz' => 'uz-latn',
	];

	const NATIONAL_OVERRIDES = [
		'de' => 'DE',
		'en' => 'US',
		'es' => 'ES',
		'fr' => 'FR',
		'nl' => 'NL',
		'ru' => 'RU',
	];

	const MIGRATE_HOME_COUNTRY = [
		'de' => 'DE',
		'es' => 'ES',
		'fr' => 'FR',
		'it' => 'IT',
		'nl' => 'NL',
	];

	const LOCALE_ISO_NORMALIZE = [
		'ge'  => 'ka',
		'ckb' => 'ku',
		'mlt' => 'mt',
	];

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public function run() {
		if ( \WPML_Settings_Failsafe_Loader::isUnrecoverable() ) {
			return false;
		}

		$wpdb  = $this->schema->get_wpdb();
		$table = $wpdb->prefix . 'icl_languages';

		if ( ! $this->has_country_model_columns() ) {
			return false;
		}

		$presets    = self::index_presets();
		$ateCountry = $this->ate_regional_country_map();

		$rows = $wpdb->get_results( "SELECT id, code, default_locale FROM `{$wpdb->prefix}icl_languages` ORDER BY id ASC" );

		$stored = $this->stored_model_columns();

		$defaultCode = $this->site_default_code();

		foreach ( (array) $rows as $row ) {
			list( $set, $formats ) = $this->row_set( $row, $presets, $ateCountry, $defaultCode );

			$id = (int) $row->id;
			if ( isset( $stored[ $id ] ) && ! $this->row_needs_write( $stored[ $id ], $set ) ) {
				continue;
			}

			$wpdb->update(
				$table,
				$set,
				[ 'id' => (int) $row->id ],
				$formats,
				[ '%d' ]
			);
		}

		self::forgetRowIdentityMemo();

		$this->record_undetermined_flag();

		$this->result = true;

		return true;
	}

	private function row_set( $row, array $presets, array $ateCountry, $defaultCode ) {
		$wpdb = $this->schema->get_wpdb();

		$legacy = (string) $row->code;

		$assignment = self::resolve( $legacy, (string) $row->default_locale, $presets );

		$ateKey     = strtolower( $legacy );
		$ateMapped  = isset( $ateCountry[ $ateKey ] );
		if ( $ateMapped ) {
			$assignment['country'] = $ateCountry[ $ateKey ];
		}

		if (
			'' !== $defaultCode
			&& 'en' !== strtolower( $legacy )
			&& strtolower( $legacy ) === strtolower( $defaultCode )
			&& null === $assignment['country']
		) {
			$localeCountry = self::locale_country( (string) $row->default_locale );
			if ( null !== $localeCountry && self::country_allowed( $assignment['code'], $localeCountry, $presets ) ) {
				$assignment['country'] = $localeCountry;
			}
		}

		$assignment['country'] = self::with_home_country_default( $legacy, $assignment['country'] );

		$set     = [
			'country' => $assignment['country'],
			'type'    => $assignment['type'],
		];
		$formats = [ null === $assignment['country'] ? null : '%s', '%s' ];

		if (
			$ateMapped
			&& null !== $assignment['country']
			&& strtolower( $legacy ) !== strtolower( $defaultCode )
		) {
			$pairLocale = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT default_locale FROM `{$wpdb->prefix}icl_language_preset_countries`
					 WHERE preset_code = %s AND country_code = %s",
					strtolower( $assignment['code'] ),
					strtoupper( $assignment['country'] )
				)
			);
			if ( $pairLocale && strcasecmp( $pairLocale, (string) $row->default_locale ) !== 0 ) {
				$set['default_locale'] = $pairLocale;
				$set['tag'] = self::identity_tag( $assignment['code'], $assignment['country'], $pairLocale );
				array_push( $formats, '%s', '%s' );
			}
		}

		return [ $set, $formats ];
	}

	private function stored_model_columns() {
		$wpdb   = $this->schema->get_wpdb();
		$stored = [];

		$rows = $wpdb->get_results( "SELECT id, country, type, default_locale, tag FROM `{$wpdb->prefix}icl_languages`" );

		foreach ( (array) $rows as $row ) {
			$stored[ (int) $row->id ] = $row;
		}

		return $stored;
	}

	private function row_needs_write( $row, array $set ) {
		foreach ( $set as $column => $value ) {
			if ( ! property_exists( $row, $column ) ) {
				return true;
			}

			if ( (string) $row->$column !== (string) $value ) {
				return true;
			}
		}

		return false;
	}

	protected static function forgetRowIdentityMemo() {
		if ( class_exists( '\WPML\LanguageEditor\LanguageCodeResolution' ) ) {
			\WPML\LanguageEditor\LanguageCodeResolution::resetStoredTags();
		}
	}

	public static function identity_tag( $code, $country, $pairLocale ) {
		$recased = \WPML\Core\Component\LanguageEditor\Domain\Bcp47::fromLegacyCode( (string) $code );
		if ( '' === $recased ) {
			return str_replace( '_', '-', (string) $pairLocale );
		}

		return \WPML\Core\Component\LanguageEditor\Domain\Bcp47::compose(
			\WPML\Core\Component\LanguageEditor\Domain\Bcp47::languageSubtag( $recased ),
			\WPML\Core\Component\LanguageEditor\Domain\Bcp47::scriptSubtag( $recased ),
			(string) $country
		);
	}

	protected function has_country_model_columns() {
		return $this->schema->does_table_exist( 'icl_languages' )
			&& $this->schema->does_column_exist( 'icl_languages', 'country' )
			&& $this->schema->does_column_exist( 'icl_languages', 'type' );
	}

	protected function record_undetermined_flag() {
		self::writeUndeterminedFlag( self::readUndeterminedNames( $this->schema->get_wpdb() ) );
	}

	public static function undeterminedLanguageNames() {
		global $wpdb;

		$names = self::readUndeterminedNames( $wpdb );
		self::writeUndeterminedFlag( $names );

		return $names;
	}

	private static function readUndeterminedNames( $wpdb ) {
		$table = $wpdb->prefix . 'icl_languages';

		return (array) $wpdb->get_col(
			"SELECT english_name FROM `{$table}`
			 WHERE active = 1 AND country IS NULL AND type = 'national' AND is_custom = 0
			 ORDER BY english_name ASC"
		);
	}

	private static function writeUndeterminedFlag( array $names ) {
		$value  = $names ? '1' : '0';
		$stored = get_option( self::OPTION_TYPE_UNDETERMINED, null );

		if ( null !== $stored && (string) $stored === $value ) {
			return;
		}

		update_option( self::OPTION_TYPE_UNDETERMINED, $value, true );
	}

	public static function countryForCode( $code, $locale = '' ) {
		$assignment = self::assignmentForCode( $code, $locale );

		return $assignment['country'];
	}

	public static function assignmentForCode( $code, $locale = '' ) {
		$assignment = self::resolve( (string) $code, (string) $locale, self::index_presets() );

		$assignment['country'] = self::with_home_country_default( (string) $code, $assignment['country'] );

		return $assignment;
	}

	private static function with_home_country_default( $legacy, $country ) {
		if ( null === $country && isset( self::MIGRATE_HOME_COUNTRY[ strtolower( $legacy ) ] ) ) {
			return self::MIGRATE_HOME_COUNTRY[ strtolower( $legacy ) ];
		}

		return $country;
	}

	protected static function resolve( $legacy, $locale, array $presets ) {
		$key = strtolower( $legacy );

		if ( isset( self::PAIR_DECODER[ $key ] ) ) {
			list( $code, $country ) = self::PAIR_DECODER[ $key ];

			return self::withType( $code, $country, $presets, 'national' );
		}

		if ( isset( self::DEFAULT_SCRIPT[ $key ] ) ) {
			$code = self::DEFAULT_SCRIPT[ $key ];

			return self::withType( $code, self::default_country( $code, $presets ), $presets );
		}

		if ( isset( $presets[ $key ] ) ) {
			$country = self::legacy_country( $key, $presets );

			return self::withType( $key, $country, $presets );
		}

		$recovered = self::recover_from_locale( $locale, $presets );
		if ( null !== $recovered ) {
			return $recovered;
		}

		return [
			'code'    => $legacy,
			'country' => self::composite_country( $legacy, $locale ),
			'type'    => 'national',
		];
	}

	private static function composite_country( $legacy, $locale ) {
		$cc = preg_match( '/-([a-z]{2})$/i', (string) $legacy, $m )
			? strtoupper( $m[1] )
			: self::locale_country( $locale );

		return ( null !== $cc && self::is_known_country( $cc ) ) ? $cc : null;
	}

	private static function is_known_country( $cc ) {
		if ( null === self::$known_countries ) {
			self::$known_countries = [];
			foreach ( \WPML\LanguageEditor\Presets\CountriesSeeder::data() as $row ) {
				if ( isset( $row['code'] ) ) {
					self::$known_countries[ strtoupper( (string) $row['code'] ) ] = true;
				}
			}
		}

		return isset( self::$known_countries[ strtoupper( (string) $cc ) ] );
	}

	private static function recover_from_locale( $locale, array $presets ) {
		$locale = trim( (string) $locale );
		if ( '' === $locale ) {
			return null;
		}

		$parts   = preg_split( '/[_-]/', $locale, 2 );
		$lang    = strtolower( $parts[0] );
		$country = isset( $parts[1] ) && '' !== $parts[1] ? strtoupper( $parts[1] ) : null;

		if ( null !== $country && ! self::is_known_country( $country ) ) {
			$country = null;
		}

		if ( isset( self::LOCALE_ISO_NORMALIZE[ $lang ] ) ) {
			$lang = self::LOCALE_ISO_NORMALIZE[ $lang ];
		}

		if ( ! isset( $presets[ $lang ] ) ) {
			return null;
		}

		if ( null === $country ) {
			$country = self::legacy_country( $lang, $presets );
		}

		return self::withType( $lang, $country, $presets );
	}

	private static function withType( $code, $country, array $presets, $forceType = null ) {
		$type = null !== $forceType
			? $forceType
			: ( isset( $presets[ $code ]['type'] ) ? (string) $presets[ $code ]['type'] : 'national' );

		return [
			'code'    => $code,
			'country' => $country,
			'type'    => $type,
		];
	}

	private static function default_country( $code, array $presets ) {
		return isset( $presets[ $code ]['default_country'] ) && '' !== (string) $presets[ $code ]['default_country']
			? (string) $presets[ $code ]['default_country']
			: null;
	}

	private function site_default_code() {
		$settings = get_option( 'icl_sitepress_settings' );

		return is_array( $settings ) && isset( $settings['default_language'] )
			? (string) $settings['default_language']
			: '';
	}

	private static function locale_country( $locale ) {
		$parts = preg_split( '/[_-]/', trim( (string) $locale ), 2 );

		return isset( $parts[1] ) && '' !== $parts[1] ? strtoupper( $parts[1] ) : null;
	}

	private static function country_allowed( $code, $country, array $presets ) {
		if ( ! isset( $presets[ $code ]['allowed_countries'] ) || ! is_array( $presets[ $code ]['allowed_countries'] ) ) {
			return true;
		}
		$allowed = array_map( 'strtoupper', $presets[ $code ]['allowed_countries'] );

		return empty( $allowed ) || in_array( strtoupper( $country ), $allowed, true );
	}

	private static function legacy_country( $code, array $presets ) {
		$mode = isset( $presets[ $code ]['country_mode'] ) ? (string) $presets[ $code ]['country_mode'] : 'required';
		if ( 'required' !== $mode ) {
			return null;
		}

		return isset( self::NATIONAL_OVERRIDES[ $code ] )
			? self::NATIONAL_OVERRIDES[ $code ]
			: self::default_country( $code, $presets );
	}


	protected static function index_presets() {
		$index = [];
		foreach ( PresetsSeeder::data() as $row ) {
			if ( isset( $row['code'] ) ) {
				$index[ strtolower( (string) $row['code'] ) ] = $row;
			}
		}

		return $index;
	}

	private function ate_regional_country_map() {
		$map = $this->stored_regional_country_map();

		try {
			$api = make( \WPML_TM_ATE_API::class );
		} catch ( \Exception $e ) {
			return $map;
		} catch ( \Throwable $e ) {
			return $map;
		}

		if ( ! is_object( $api ) || ! method_exists( $api, 'get_language_mapping' ) ) {
			return $map;
		}

		try {
			$maybeRecords = $api->get_language_mapping();
		} catch ( \Throwable $e ) {
			update_option( self::OPTION_ATE_MAPPING_DEFERRED, true, true );

			return $map;
		}

		if ( $maybeRecords->isNothing() ) {
			if ( \WPML\TM\ATE\ClonedSites\ReconnectState::isReconnecting() ) {
				update_option( self::OPTION_ATE_MAPPING_DEFERRED, true, true );
			}

			return $map;
		}

		$records = $maybeRecords->getOrElse( [] );

		foreach ( (array) $records as $record ) {
			$record = (array) $record;
			$source = isset( $record['source_code'] ) ? strtolower( (string) $record['source_code'] ) : '';
			$target = isset( $record['target_code'] ) ? strtolower( (string) $record['target_code'] ) : '';
			if ( '' === $source || '' === $target ) {
				continue;
			}

			$country = $this->country_of_ate_target( $target );
			if ( null !== $country ) {
				$map[ $source ] = $country;
			}
		}

		update_option( self::OPTION_ATE_MAPPING_DEFERRED, 0, true );

		return $map;
	}

	private function live_ate_regional_country_map() {
		try {
			$api = make( \WPML_TM_ATE_API::class );
		} catch ( \Exception $e ) {
			return null;
		} catch ( \Throwable $e ) {
			return null;
		}

		if ( ! is_object( $api ) || ! method_exists( $api, 'get_language_mapping' ) ) {
			return null;
		}

		try {
			$maybeRecords = $api->get_language_mapping();
		} catch ( \Throwable $e ) {
			return null;
		}

		if ( $maybeRecords->isNothing() ) {
			return null;
		}

		$map = [];

		foreach ( (array) $maybeRecords->getOrElse( [] ) as $record ) {
			$record = (array) $record;
			$source = isset( $record['source_code'] ) ? strtolower( (string) $record['source_code'] ) : '';
			$target = isset( $record['target_code'] ) ? strtolower( (string) $record['target_code'] ) : '';
			if ( '' === $source || '' === $target ) {
				continue;
			}

			$country = $this->country_of_ate_target( $target );
			if ( null !== $country ) {
				$map[ $source ] = $country;
			}
		}

		return $map;
	}

	private function stored_regional_country_map() {
		$map = [];

		try {
			$mappings = \WPML\Setup\Option::getLanguageMappings();
		} catch ( \Throwable $e ) {
			return $map;
		}

		foreach ( (array) $mappings as $mapping ) {
			$mapping = is_array( $mapping ) ? (object) $mapping : $mapping;
			if ( ! is_object( $mapping ) ) {
				continue;
			}
			$source = isset( $mapping->sourceCode ) ? strtolower( (string) $mapping->sourceCode ) : '';
			$target = isset( $mapping->targetCode ) ? strtolower( (string) $mapping->targetCode ) : '';
			if ( '' === $source || '' === $target ) {
				continue;
			}

			$country = $this->country_of_ate_target( $target );
			if ( null !== $country ) {
				$map[ $source ] = $country;
			}
		}

		return $map;
	}

	private function country_of_ate_target( $target ) {
		if ( ! preg_match( '/^[a-z]{2,3}-([a-z]{2})$/', (string) $target, $m ) ) {
			return null;
		}

		$country = strtoupper( $m[1] );

		return self::is_known_country( $country ) ? $country : null;
	}

	public static function reapplyAteCountryMappingIfDeferred() {
		if ( ! get_option( self::OPTION_ATE_MAPPING_DEFERRED, false ) ) {
			return;
		}

		global $wpdb;

		( new self( [ new \WPML_Upgrade_Schema( $wpdb ) ] ) )->reapplyDeferredAteCountryMapping();
	}

	public static function reapplyAteCountryMappingIfDeferredThrottled() {
		if ( \WPML_Settings_Failsafe_Loader::isUnrecoverable() ) {
			return;
		}

		if ( ! get_option( self::OPTION_ATE_MAPPING_DEFERRED, false ) ) {
			return;
		}

		if ( (int) get_option( self::OPTION_ATE_MAPPING_REAPPLY_NEXT_ATTEMPT, 0 ) > time() ) {
			return;
		}

		global $wpdb;

		( new self( [ new \WPML_Upgrade_Schema( $wpdb ) ] ) )->reapplyDeferredAteCountryMapping();
	}

	public function reapplyDeferredAteCountryMapping() {
		if ( \WPML_Settings_Failsafe_Loader::isUnrecoverable() ) {
			return false;
		}

		if ( ! $this->has_country_model_columns() ) {
			return false;
		}

		$live = $this->live_ate_regional_country_map();
		if ( null === $live ) {
			update_option(
				self::OPTION_ATE_MAPPING_REAPPLY_NEXT_ATTEMPT,
				time() + self::ATE_MAPPING_REAPPLY_BACKOFF,
				true
			);

			return false;
		}

		update_option( self::OPTION_ATE_MAPPING_REAPPLY_NEXT_ATTEMPT, 0, true );

		$this->apply_ate_country_overrides( $live );

		update_option( self::OPTION_ATE_MAPPING_DEFERRED, 0, true );

		return true;
	}

	private function apply_ate_country_overrides( array $map ) {
		$wpdb        = $this->schema->get_wpdb();
		$table       = $wpdb->prefix . 'icl_languages';
		$presets     = self::index_presets();
		$defaultCode = $this->site_default_code();
		$written     = false;

		foreach ( $map as $code => $country ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, code, default_locale, country, type, tag
					 FROM `{$wpdb->prefix}icl_languages` WHERE LOWER(code) = %s",
					strtolower( (string) $code )
				)
			);

			$row = $rows ? reset( $rows ) : null;
			if ( ! is_object( $row ) ) {
				continue;
			}

			if (
				property_exists( $row, 'country' )
				&& strtoupper( (string) $row->country ) === strtoupper( (string) $country )
			) {
				continue;
			}

			list( $set, $formats ) = $this->row_set( $row, $presets, $map, $defaultCode );

			if ( ! $this->row_needs_write( $row, $set ) ) {
				continue;
			}

			$wpdb->update(
				$table,
				$set,
				[ 'id' => (int) $row->id ],
				$formats,
				[ '%d' ]
			);

			$written = true;
		}

		if ( $written ) {
			self::forgetRowIdentityMemo();
		}
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return $this->run();
	}

	public function run_frontend() {
		return $this->run();
	}

	public function get_results() {
		return $this->result;
	}
}
