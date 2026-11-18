<?php

namespace WPML\ST\Upgrade\Command;

use WPML\Element\API\Languages;
use WPML\ST\DB\Mappers\StringsRetrieve;
use WPML\ST\MO\File\Manager;
use WPML\ST\MO\File\ManagerFactory;
use function WPML\Container\make;

class RegenerateMoFilesWithNativeCopies implements \IWPML_St_Upgrade_Command {

	const PROGRESS_OPTION = 'wpml_st_12209_mo_with_native_copies_progress';

	const TIME_BUDGET = 5;

	private $manager;

	private $getFiles;

	private $time;

	public function __construct( ?Manager $manager = null, ?callable $getFiles = null, ?callable $time = null ) {
		$this->manager  = $manager;
		$this->getFiles = $getFiles ?: [ self::class, 'getDefaultFiles' ];
		$this->time     = $time ?: 'time';
	}

	public function run() {
		try {
			$files = call_user_func( $this->getFiles );
		} catch ( \RuntimeException $e ) {
			return false;
		}

		if ( ! $files ) {
			delete_option( self::PROGRESS_OPTION );

			return true;
		}

		$done    = $this->getProgress();
		$started = call_user_func( $this->time );
		$manager = $this->manager ?: ManagerFactory::create();
		$seen    = [];
		$handled = 0;

		foreach ( $files as $file ) {
			list( $domain, $locale ) = $file;
			$key                     = $domain . '|' . $locale;

			$filepath = $manager->getFilepath( $domain, $locale );
			if ( isset( $seen[ $filepath ] ) || in_array( $key, $done, true ) ) {
				continue;
			}
			$seen[ $filepath ] = true;

			$elapsed = call_user_func( $this->time ) - $started;
			if ( $handled > 0 && $elapsed >= self::TIME_BUDGET ) {
				$this->saveProgress( $done );

				return false;
			}

			try {
				$manager->add( $domain, $locale );
			} catch ( \RuntimeException $e ) {
				$this->saveProgress( $done );

				return false;
			}

			$done[] = $key;
			$handled++;
			$this->saveProgress( $done );
		}

		delete_option( self::PROGRESS_OPTION );

		return true;
	}

	public function run_ajax() {
		return $this->run();
	}

	public function run_frontend() {
	}

	public static function get_command_id() {
		return __CLASS__;
	}

	public static function getDefaultFiles() {
		global $wpdb;

		$active   = array_map( 'strval', array_keys( (array) Languages::getActive() ) );
		$wpLocale = make( \WPML_Locale::class );

		$files = self::getFilesToRewrite(
			new StringsRetrieve( $wpdb ),
			RegenerateMoFilesWithoutNativeCopies::getDefaultDomains(),
			RegenerateMoFilesWithoutNativeCopies::nativeTextLookup( $wpdb ),
			function ( $language ) use ( $active, $wpLocale ) {
				return in_array( (string) $language, $active, true ) ? $wpLocale->get_locale( $language ) : false;
			}
		);

		if ( ! empty( $wpdb->last_error ) ) {
			throw new \RuntimeException( 'The icl_strings listing query failed.' );
		}

		return $files;
	}

	public static function getFilesToRewrite( StringsRetrieve $strings, array $domains, callable $hasNativeText, callable $localeOf ) {
		$candidates = [];
		foreach ( $domains as $domain ) {
			$domain = (string) $domain;
			if (
				'' === $domain
				|| StringsRetrieve::omitsNativeCopies( $domain )
				|| StringsRetrieve::isAdminTextsDomain( $domain )
				|| ! call_user_func( $hasNativeText, $domain )
			) {
				continue;
			}
			$candidates[] = $domain;
		}

		$files = [];
		foreach ( $strings->getDomainLanguagesWithChanges( $candidates ) as $row ) {
			$locale = call_user_func( $localeOf, $row['languageCode'] );
			if ( $locale ) {
				$files[] = [ (string) $row['domain'], (string) $locale ];
			}
		}

		return $files;
	}

	private function getProgress() {
		$done = get_option( self::PROGRESS_OPTION, [] );

		return is_array( $done ) ? $done : [];
	}

	private function saveProgress( array $done ) {
		update_option( self::PROGRESS_OPTION, $done, false );
	}
}
