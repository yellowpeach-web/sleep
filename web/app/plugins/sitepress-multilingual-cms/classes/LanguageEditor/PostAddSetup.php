<?php

namespace WPML\LanguageEditor;

use WPML\Core\BackgroundTask\Command\UpdateBackgroundTask;
use WPML\Core\BackgroundTask\Repository\BackgroundTaskRepository;
use WPML\Core\BackgroundTask\Service\BackgroundTaskService;
use WPML\Language\ActiveLanguagesReadModel;
use WPML\Localization\DownloadLanguagePacksTask;
use WPML\Localization\TranslationsApiBreaker;
use WPML\Setup\Option;
use WPML\WP\OptionManager;
use SitePress_Setup;
use WPML_Download_Localization;
use WPML_Languages_Notices;
use function WPML\Container\make;

class PostAddSetup {

	const MISSING_PACKS_OPTION = '_wpml_le_missing_lang_packs';

	const PACK_REQUESTS_OPTION = 'wpml_language_pack_requests';

	const PENDING_SITE_LOCALE_OPTION = '_wpml_le_pending_site_locale';

	public static function run( $code ) {
		global $wpdb, $sitepress;

		$code = (string) $code;
		if ( '' === $code ) {
			return;
		}

		$locale = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT default_locale FROM {$wpdb->prefix}icl_languages WHERE code = %s LIMIT 1",
				$code
			)
		);
		if ( $locale ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT code FROM {$wpdb->prefix}icl_locale_map WHERE code = %s LIMIT 1",
					$code
				)
			);
			if ( $exists ) {
				$wpdb->update( $wpdb->prefix . 'icl_locale_map', [ 'locale' => $locale ], [ 'code' => $code ] );
			} else {
				$wpdb->insert( $wpdb->prefix . 'icl_locale_map', [ 'code' => $code, 'locale' => $locale ] );
			}
		}

		LanguageNames::seedNewLanguage( $code );

		if ( $sitepress && $sitepress->is_setup_complete() ) {
			$sitepress->get_wpml_locale()->reset_cached_data();

			ActiveLanguagesReadModel::invalidate();
		}

		if ( class_exists( SitePress_Setup::class ) && $sitepress && $sitepress->is_setup_complete() ) {
			SitePress_Setup::insert_default_category( $code );
		}


	}

	public static function deferDownloadLanguagePacks( array $codes ) {
		try {
			$codes = array_values( array_unique( array_filter( array_map( 'strval', $codes ) ) ) );
			if ( ! $codes || ! class_exists( WPML_Download_Localization::class ) ) {
				return false;
			}

			self::requestPackDownloads( $codes );

			$repository = make( BackgroundTaskRepository::class );
			$existing   = $repository->getLastIncompletedByType( DownloadLanguagePacksTask::class );

			if ( $existing ) {
				$update = make( UpdateBackgroundTask::class );
				$update->saveStatusRestart( $existing );

				return true;
			}

			$endpoint = make( DownloadLanguagePacksTask::class );
			$service = make( BackgroundTaskService::class );

			$task = $service->add(
				$endpoint,
				wpml_collect(
					array(
						'coreDone'    => false,
						'donePlugins' => false,
						'notFounds'   => array(),
					)
				)
			);

			return (bool) $task;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	public static function syncSiteLocaleOption() {
		$expected = self::expectedSiteLocale();
		if ( null === $expected ) {
			return;
		}

		$before = get_option( 'WPLANG' );
		if ( $before === $expected ) {
			delete_option( self::PENDING_SITE_LOCALE_OPTION );

			return;
		}

		update_option( 'WPLANG', $expected );

		if ( get_option( 'WPLANG' ) === $expected ) {
			delete_option( self::PENDING_SITE_LOCALE_OPTION );

			return;
		}

		update_option(
			self::PENDING_SITE_LOCALE_OPTION,
			array(
				'locale'   => $expected,
				'previous' => $before,
			),
			false
		);
	}

	public static function applyPendingSiteLocale() {
		$pending = get_option( self::PENDING_SITE_LOCALE_OPTION );
		if ( ! is_array( $pending ) || ! isset( $pending['locale'] ) || ! array_key_exists( 'previous', $pending ) ) {
			return;
		}

		delete_option( self::PENDING_SITE_LOCALE_OPTION );

		if ( $pending['locale'] !== self::expectedSiteLocale() ) {
			return;
		}

		if ( get_option( 'WPLANG' ) !== $pending['previous'] ) {
			return;
		}

		update_option( 'WPLANG', $pending['locale'] );
	}

	private static function expectedSiteLocale() {
		global $sitepress;

		if ( ! $sitepress ) {
			return null;
		}

		$locale = $sitepress->get_locale( $sitepress->get_default_language() );
		if ( ! $locale ) {
			return null;
		}

		return 'en_US' === $locale ? '' : $locale;
	}

	public static function requestPackDownloads( array $codes ) {
		$now = microtime( true );

		return (array) ( new OptionManager() )->mutateRaw(
			self::PACK_REQUESTS_OPTION,
			function ( $current ) use ( $codes, $now ) {
				$current = is_array( $current ) ? $current : array();

				foreach ( $codes as $code ) {
					$current[ (string) $code ] = $now;
				}

				return $current;
			},
			false
		);
	}

	public static function packRequests() {
		$requests = get_option( self::PACK_REQUESTS_OPTION, array() );

		return is_array( $requests ) ? $requests : array();
	}

	public static function takeCoveredPackRequests( $passStartedAt ) {
		$hasNewer = false;

		( new OptionManager() )->mutateRaw(
			self::PACK_REQUESTS_OPTION,
			function ( $current ) use ( $passStartedAt, &$hasNewer ) {
				$current = is_array( $current ) ? $current : array();
				$kept    = array();

				foreach ( $current as $code => $requestedAt ) {
					if ( (float) $requestedAt > (float) $passStartedAt ) {
						$kept[ $code ] = $requestedAt;
						$hasNewer      = true;
					}
				}

				return $kept;
			},
			false
		);

		return $hasNewer;
	}

	public static function download( array $codes ) {
		global $sitepress;

		if ( ! class_exists( WPML_Download_Localization::class ) || ! $sitepress ) {
			return;
		}

		$langsByCode = self::languagesByCode( $codes );
		if ( ! $langsByCode ) {
			return;
		}

		TranslationsApiBreaker::arm();
		try {
			$downloader = self::downloaderFor( $langsByCode );
			$downloader->download_language_packs();
			self::refreshMissingPacksNotice( $langsByCode, $downloader->get_not_founds() );
		} finally {
			TranslationsApiBreaker::disarm();
		}
	}

	public static function languagesByCode( array $codes ) {
		global $sitepress;

		if ( ! $sitepress ) {
			return array();
		}

		$langsByCode = array();
		foreach ( array_unique( array_map( 'strval', $codes ) ) as $code ) {
			$lang = $sitepress->get_language_details( $code );
			if ( $lang ) {
				$langsByCode[ $code ] = $lang;
			}
		}

		return $langsByCode;
	}

	public static function activeLanguagesByCode( array $codes ) {
		global $sitepress;

		if ( ! $sitepress ) {
			return array();
		}

		$active      = (array) $sitepress->get_active_languages();
		$langsByCode = array();

		foreach ( array_unique( array_map( 'strval', $codes ) ) as $code ) {
			if ( isset( $active[ $code ] ) ) {
				$langsByCode[ $code ] = $active[ $code ];
			}
		}

		return $langsByCode;
	}

	public static function downloaderFor( array $langsByCode ) {
		global $sitepress;

		return new WPML_Download_Localization( $langsByCode, $sitepress->get_default_language() );
	}

	public static function refreshMissingPacksNotice( array $langsByCode, array $notFounds ) {
		global $sitepress;

		if ( ! class_exists( WPML_Languages_Notices::class ) || ! function_exists( 'wpml_get_admin_notices' ) ) {
			return;
		}

		$stored = get_option( self::MISSING_PACKS_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$missing = array();
		foreach ( $notFounds as $notFound ) {
			if ( isset( $notFound['code'] ) ) {
				$missing[ (string) $notFound['code'] ] = true;
			}
		}

		foreach ( $langsByCode as $code => $lang ) {
			if ( isset( $missing[ (string) $code ] ) ) {
				$stored[ $code ] = $lang;
			} else {
				unset( $stored[ $code ] );
			}
		}

		$activeCodes = array_keys( (array) $sitepress->get_active_languages() );
		$stored      = array_intersect_key( $stored, array_flip( $activeCodes ) );

		update_option( self::MISSING_PACKS_OPTION, $stored, false );

		$notices = new WPML_Languages_Notices( wpml_get_admin_notices() );
		$notices->missing_languages( array_values( $stored ) );
	}
}
