<?php

namespace WPML\LanguageEditor\Presets;

use WPML\LanguageEditor\LanguageNames;
use WPML\Upgrade\Commands\SeedCountryTranslations;
use WPML\Utilities\Lock;
use WPML_TM_ATE_AMS_Endpoints;
use WPML_TM_ATE_API;
use function WPML\Container\make;

class CatalogueSyncRunner {

	const SECTIONS = array(
		CatalogueVersionStore::SECTION_LANGUAGES,
		CatalogueVersionStore::SECTION_LANGUAGE_TRANSLATIONS,
		CatalogueVersionStore::SECTION_COUNTRY_TRANSLATIONS,
		CatalogueVersionStore::SECTION_FLAGS,
	);

	const NO_DESCRIPTOR_STALE_AFTER = 86400;

	const PROBE_TTL = 900;

	const PROBE_TRANSIENT_PREFIX = 'wpml_catalogue_probe_';

	const PROBE_STALE = 'stale';
	const PROBE_FRESH = 'fresh';

	const LOCK_NAME = 'language_catalogue_sync';

	const LOCK_TIME = 60;

	private $api;

	private $store;

	private $wpdb;

	private $catalogueBaseUrl;

	private $feedSource;

	private $stoodDown = false;

	public function __construct( WPML_TM_ATE_API $api, CatalogueVersionStore $store, $wpdb, $catalogueBaseUrl = '', ?CatalogueFeedSource $feedSource = null ) {
		$this->api              = $api;
		$this->store            = $store;
		$this->wpdb             = $wpdb;
		$this->catalogueBaseUrl = (string) $catalogueBaseUrl;
		$this->feedSource       = $feedSource;
	}

	public static function create() {
		global $wpdb;

		return new self(
			make( WPML_TM_ATE_API::class ),
			new CatalogueVersionStore(),
			$wpdb,
			make( WPML_TM_ATE_AMS_Endpoints::class )->get_AMS_base_url()
		);
	}

	protected function feedSource() {
		if ( null === $this->feedSource ) {
			$this->feedSource = CatalogueFeedSource::create();
		}

		return $this->feedSource;
	}

	public function run() {
		$lock = $this->lock();
		if ( ! $lock->create( self::LOCK_TIME ) ) {
			return false;
		}

		$this->stoodDown = false;

		try {
			$this->syncSections( $this->feedSource()->descriptor() );

			if ( $this->stoodDown ) {
				delete_transient( $this->probeKey() );
			} else {
				$this->rememberProbe( false );
			}
		} finally {
			$lock->release();
		}

		do_action( 'wpml_language_catalogue_synced' );

		return true;
	}

	private function syncSections( $remote ) {
		if ( null === $remote ) {
			$this->ateFallbackPass();

			return;
		}

		if ( $this->shouldRun( CatalogueVersionStore::SECTION_LANGUAGES, $remote ) ) {
			$feed = $this->feedSource()->languages();

			if ( null === $feed ) {
				$this->ateFallbackPass();
			} else {
				$result = $this->applyCatalogueFeed( $feed );
				$this->noteStandDown( $result );

				if ( $feed->isFullAuthority() && empty( $result['applyFailed'] ) && empty( $result['stoodDown'] ) ) {
					$this->store->setVersion( CatalogueVersionStore::SECTION_LANGUAGES, (int) $remote[ CatalogueVersionStore::SECTION_LANGUAGES ] );
				}

				if ( ! empty( $result['addedCountries'] ) ) {
					$this->store->clearVersion( CatalogueVersionStore::SECTION_COUNTRY_TRANSLATIONS );
					$this->store->clearVersion( CatalogueVersionStore::SECTION_FLAGS );
				}
			}
		}

		$this->runSection(
			CatalogueVersionStore::SECTION_LANGUAGE_TRANSLATIONS,
			$remote,
			[ $this->api, 'get_language_translations' ],
			[ $this, 'applyLanguageTranslations' ]
		);

		$this->runSection(
			CatalogueVersionStore::SECTION_COUNTRY_TRANSLATIONS,
			$remote,
			[ $this->api, 'get_country_translations' ],
			[ $this, 'applyCountryTranslations' ]
		);

		$this->runSection(
			CatalogueVersionStore::SECTION_FLAGS,
			$remote,
			[ $this->api, 'get_country_flags' ],
			[ $this, 'applyFlags' ]
		);
	}

	public function isStale() {
		$remembered = get_transient( $this->probeKey() );
		if ( self::PROBE_STALE === $remembered || self::PROBE_FRESH === $remembered ) {
			return self::PROBE_STALE === $remembered;
		}

		$stale = $this->isStaleAgainst( $this->feedSource()->descriptor() );
		$this->rememberProbe( $stale );

		return $stale;
	}

	private function isStaleAgainst( $remote ) {
		if ( null === $remote ) {
			$syncedAt = $this->store->getSyncedAt( CatalogueVersionStore::SECTION_LANGUAGES );

			return ( time() - $syncedAt ) >= self::NO_DESCRIPTOR_STALE_AFTER;
		}

		foreach ( self::SECTIONS as $section ) {
			if ( $this->shouldRun( $section, $remote ) ) {
				return true;
			}
		}

		return false;
	}

	private function rememberProbe( $stale ) {
		set_transient( $this->probeKey(), $stale ? self::PROBE_STALE : self::PROBE_FRESH, self::PROBE_TTL );
	}

	private function probeKey() {
		return self::PROBE_TRANSIENT_PREFIX . md5( $this->catalogueBaseUrl );
	}

	protected function lock() {
		return make( Lock::class, [ ':name' => self::LOCK_NAME ] );
	}

	private function shouldRun( $section, array $remote ) {
		if ( ! isset( $remote[ $section ] ) ) {
			return false;
		}

		$stored = $this->store->getVersion( $section );

		return null === $stored || (int) $remote[ $section ] > $stored;
	}

	private function runSection( $section, array $remote, callable $fetch, callable $apply ) {
		if ( ! $this->shouldRun( $section, $remote ) ) {
			return;
		}

		$payload = call_user_func( $fetch, $this->store->getVersion( $section ) );
		if ( null === $payload ) {
			return;
		}

		if ( $payload['data'] && true !== call_user_func( $apply, $payload['data'] ) ) {
			return;
		}

		$version = $payload['version'] > 0 ? (int) $payload['version'] : (int) $remote[ $section ];
		$this->store->setVersion( $section, $version );
	}

	private function ateFallbackPass() {
		$result = $this->reconcileLanguages();
		$this->noteStandDown( $result );

		if ( ! empty( $result['checked'] ) && empty( $result['applyFailed'] ) && empty( $result['stoodDown'] ) ) {
			$this->store->markSynced( CatalogueVersionStore::SECTION_LANGUAGES );
		}
	}

	private function noteStandDown( array $result ) {
		if ( ! empty( $result['stoodDown'] ) ) {
			$this->stoodDown = true;
		}
	}

	protected function sync() {
		return AteCatalogueSync::create();
	}

	protected function reconcileLanguages() {
		return $this->sync()->reconcileWith( $this->feedSource()->ateFallback() );
	}

	protected function applyCatalogueFeed( CatalogueFeed $feed ) {
		$sync = $this->sync();

		$applied = $sync->applyAmsFeed( $feed );
		$vouched = $sync->applyVouchState( $feed );

		return array_merge(
			[
				'source'       => $feed->source,
				'authority'    => $feed->authority,
				'checked'      => $feed->checked,
				'count'        => $feed->count,
				'unrecognized' => $feed->unrecognized,
			],
			$applied,
			$vouched,
			[
				'applyFailed' => ! empty( $applied['applyFailed'] ) || ! empty( $vouched['applyFailed'] ),
				'stoodDown'   => ! empty( $applied['stoodDown'] ) || ! empty( $vouched['stoodDown'] ),
			]
		);
	}

	protected function applyLanguageTranslations( array $data ) {
		$wpdb  = $this->wpdb;
		$table = $wpdb->prefix . LanguageNames::TABLE;
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return false;
		}

		LanguageNames::overlayBlocks( self::transpose( $data ) );

		$languages_table = $wpdb->prefix . 'icl_languages';

		$stored = (array) $wpdb->get_col( "SELECT DISTINCT language_code FROM `{$table}`" );
		$known = (array) $wpdb->get_col( "SELECT code FROM `{$languages_table}`" );
		$codes = array_filter(
			array_map( 'strtolower', array_map( 'strval', array_keys( $data ) ) ),
			[ self::class, 'isLanguageCode' ]
		);

		$columns = (array) $wpdb->get_col( "SELECT DISTINCT display_language_code FROM `{$table}`" );
		$active = (array) $wpdb->get_col( "SELECT code FROM `{$languages_table}` WHERE active = 1" );
		$blocks = array_keys( self::transpose( $data ) );

		return LanguageNames::seed(
			array_values( array_unique( array_merge( $codes, $stored, $known ) ) ),
			array_values( array_unique( array_merge( $blocks, $columns, $active ) ) )
		);
	}

	protected function applyCountryTranslations( array $data ) {
		$wpdb  = $this->wpdb;
		$table = $wpdb->prefix . SeedCountryTranslations::TABLE_NAME;
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return false;
		}

		$ok = true;
		foreach ( $data as $countryCode => $names ) {
			$countryCode = strtoupper( trim( (string) $countryCode ) );
			if ( ! self::isCountryCode( $countryCode ) || ! is_array( $names ) ) {
				continue;
			}
			foreach ( $names as $displayCode => $name ) {
				$displayCode = strtolower( trim( (string) $displayCode ) );
				$name        = trim( (string) $name );
				if ( '' === $displayCode || '' === $name ) {
					continue;
				}

				$inserted = $wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO `{$table}` (country_code, display_language_code, name) VALUES (%s, %s, %s)",
						$countryCode,
						$displayCode,
						$name
					)
				);
				$ok = $ok && ( false !== $inserted );
			}
		}

		return $ok;
	}

	protected function applyFlags( array $data ) {
		$flags = [];
		foreach ( $data as $countryCode => $svg ) {
			$countryCode = strtoupper( trim( (string) $countryCode ) );
			if ( self::isCountryCode( $countryCode ) ) {
				$flags[ $countryCode ] = $svg;
			}
		}

		return false !== ( new RemoteCountryFlags( $this->wpdb ) )->apply( $flags );
	}

	public static function transpose( array $data ) {
		$blocks = [];
		foreach ( $data as $code => $names ) {
			$code = strtolower( trim( (string) $code ) );
			if ( ! is_array( $names ) || ! self::isLanguageCode( $code ) ) {
				continue;
			}
			foreach ( $names as $display => $name ) {
				$display = strtolower( trim( (string) $display ) );
				if ( ! self::isLanguageCode( $display ) ) {
					continue;
				}
				$blocks[ $display ][ $code ] = (string) $name;
			}
		}

		return $blocks;
	}

	public static function isLanguageCode( $code ) {
		$code = (string) $code;

		return LanguageNames::fitsCodeCap( $code )
			&& (bool) preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $code );
	}

	public static function isCountryCode( $code ) {
		return (bool) preg_match( '/^[A-Z]{2}$/', (string) $code );
	}
}
