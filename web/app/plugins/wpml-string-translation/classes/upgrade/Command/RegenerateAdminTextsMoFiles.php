<?php

namespace WPML\ST\Upgrade\Command;

use WPML\Element\API\Languages;
use WPML\ST\DB\Mappers\StringsRetrieve;
use WPML\ST\MO\File\Manager;
use WPML\ST\MO\File\ManagerFactory;
use function WPML\Container\make;

class RegenerateAdminTextsMoFiles implements \IWPML_St_Upgrade_Command {

	const PROGRESS_OPTION = 'wpml_st_12156_admin_texts_mo_progress';

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
		$files = call_user_func( $this->getFiles );
		if ( ! $files ) {
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

		return self::getFilesToRewrite(
			$wpdb,
			function ( $language ) use ( $active, $wpLocale ) {
				return in_array( (string) $language, $active, true ) ? $wpLocale->get_locale( $language ) : false;
			}
		);
	}

	public static function getFilesToRewrite( \wpdb $wpdb, callable $localeOf ) {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT BINARY s.context AS `domain`, st.language AS `language`
				FROM {$wpdb->prefix}icl_string_translations st
				INNER JOIN {$wpdb->prefix}icl_strings s ON s.id = st.string_id
				WHERE s.context LIKE %s
					AND st.mo_string IS NOT NULL AND st.mo_string <> ''
					AND st.status IN (%d, %d) AND st.value IS NOT NULL
					AND s.language <> st.language
				ORDER BY `domain`, `language`",
				$wpdb->esc_like( StringsRetrieve::ADMIN_TEXTS_PREFIX ) . '%',
				ICL_TM_COMPLETE,
				ICL_TM_NEEDS_UPDATE
			),
			ARRAY_A
		);

		$files = [];
		foreach ( (array) $rows as $row ) {
			$locale = call_user_func( $localeOf, $row['language'] );
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
