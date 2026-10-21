<?php

namespace WPML\ST\Upgrade\Command;

use WPML\ST\DB\Mappers\StringsRetrieve;
use WPML\ST\MO\File\Manager;
use WPML\ST\MO\File\ManagerFactory;
use WPML\ST\TranslationFile\Domains;
use function WPML\Container\make;

class RegenerateMoFilesWithoutNativeCopies implements \IWPML_St_Upgrade_Command {

	const PROGRESS_OPTION = 'wpml_st_10615_mo_without_native_copies_progress';

	const TIME_BUDGET = 5;

	private $manager;

	private $getActiveLocales;

	private $getDomains;

	private $time;

	private $hasFiles;

	private $hasNativeText;

	public function __construct(
		?Manager $manager = null,
		?callable $getActiveLocales = null,
		?callable $getDomains = null,
		?callable $time = null,
		?callable $hasFiles = null,
		?callable $hasNativeText = null
	) {
		$this->manager          = $manager;
		$this->getActiveLocales = $getActiveLocales ?: [ RegenerateDefaultMoForWpRouting::class, 'getDefaultActiveLocales' ];
		$this->getDomains       = $getDomains ?: [ self::class, 'getDefaultDomains' ];
		$this->time             = $time ?: 'time';
		$this->hasFiles         = $hasFiles ?: [ Manager::class, 'hasFiles' ];
		$this->hasNativeText    = $hasNativeText ?: function ( $domain ) {
			global $wpdb;

			return self::domainHasNativeText( (string) $domain, $wpdb );
		};
	}

	public function run() {
		if ( ! call_user_func( $this->hasFiles ) ) {
			return true;
		}

		$done    = $this->getProgress();
		$started = call_user_func( $this->time );
		$manager = $this->manager ?: ManagerFactory::create();

		foreach ( $this->getExistingFiles( $manager ) as $file ) {
			list( $domain, $locale ) = $file;
			$key                     = $domain . '|' . $locale;

			if ( in_array( $key, $done, true ) ) {
				continue;
			}

			if ( call_user_func( $this->time ) - $started >= self::TIME_BUDGET ) {
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

	public static function getDefaultDomains() {
		$domains = make( Domains::class )->getMODomains()->toArray();
		$domains = is_array( $domains ) ? $domains : [];

		return array_values( array_unique( array_merge( $domains, [ StringsRetrieve::CONTEXT_DEFAULT ] ) ) );
	}

	private function getExistingFiles( Manager $manager ) {
		$files = [];

		foreach ( call_user_func( $this->getActiveLocales ) as $locale ) {
			foreach ( call_user_func( $this->getDomains ) as $domain ) {
				$domain = (string) $domain;
				if ( '' === $domain ) {
					continue;
				}

				$filepath = $manager->getFilepath( $domain, $locale );
				if (
					! isset( $files[ $filepath ] )
					&& $manager->get( $domain, $locale )
					&& $this->hasNativeTextCached( $domain )
				) {
					$files[ $filepath ] = [ $domain, $locale ];
				}
			}
		}

		return array_values( $files );
	}

	private $nativeTextAnswers = [];

	private function hasNativeTextCached( $domain ) {
		$key = strtolower( $domain );
		if ( ! array_key_exists( $key, $this->nativeTextAnswers ) ) {
			$this->nativeTextAnswers[ $key ] = (bool) call_user_func( $this->hasNativeText, $domain );
		}

		return $this->nativeTextAnswers[ $key ];
	}

	public static function domainHasNativeText( $domain, \wpdb $wpdb ) {
		$contexts = StringsRetrieve::CONTEXT_DEFAULT === strtolower( $domain )
			? [ StringsRetrieve::CONTEXT_DEFAULT, StringsRetrieve::CONTEXT_WORDPRESS ]
			: [ $domain ];

		foreach ( $contexts as $context ) {
			$found = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT 1 FROM {$wpdb->prefix}icl_strings s
					 INNER JOIN {$wpdb->prefix}icl_string_translations st ON st.string_id = s.id
					 WHERE s.context = %s AND st.mo_string IS NOT NULL AND st.mo_string <> ''
					 LIMIT 1",
					$context
				)
			);

			if ( $found ) {
				return true;
			}
		}

		return false;
	}

	private function getProgress() {
		$done = get_option( self::PROGRESS_OPTION, [] );

		return is_array( $done ) ? $done : [];
	}

	private function saveProgress( array $done ) {
		update_option( self::PROGRESS_OPTION, $done, false );
	}
}
