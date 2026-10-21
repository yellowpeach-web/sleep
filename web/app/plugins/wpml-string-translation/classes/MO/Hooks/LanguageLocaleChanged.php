<?php

namespace WPML\ST\MO\Hooks;

use WPML\ST\MO\File\Manager;
use WPML\ST\MO\Generate\DomainsAndLanguagesRepository;
use WPML\ST\TranslationFile\Domains;
use WPML\ST\TranslationFile\Manager as TranslationFileManager;
use WPML\ST\TranslationFile\PluralForms;
use function WPML\Container\make;

class LanguageLocaleChanged implements \IWPML_Action {

	const OPTION = 'wpml_st_language_locales';

	private $domainsAndLanguagesRepository;

	private $manager;

	private $getLocales;

	private $resetLocaleCache;

	private $scriptManager;

	private $canWriteFiles;

	private $copyPluralForms;

	private $getScriptDomains;

	public function __construct(
		DomainsAndLanguagesRepository $domainsAndLanguagesRepository,
		Manager $manager,
		?callable $getLocales = null,
		?callable $resetLocaleCache = null,
		?TranslationFileManager $scriptManager = null,
		?callable $canWriteFiles = null,
		?callable $copyPluralForms = null,
		?callable $getScriptDomains = null
	) {
		$this->domainsAndLanguagesRepository = $domainsAndLanguagesRepository;
		$this->manager                       = $manager;
		$this->getLocales                    = $getLocales ?: function () {
			return wpml_collect( make( \WPML_Locale::class )->get_all_locales() )->toArray();
		};
		$this->resetLocaleCache              = $resetLocaleCache ?: function () {
			make( \WPML_Locale::class )->reset_cached_data();
		};
		$this->scriptManager                 = $scriptManager;
		$this->canWriteFiles                 = $canWriteFiles ?: function () {
			$subdir = TranslationFileManager::getSubdir();

			return is_dir( $subdir ) ? wp_is_writable( $subdir ) : wp_is_writable( dirname( $subdir ) );
		};
		$this->copyPluralForms               = $copyPluralForms ?: [ PluralForms::class, 'copyLocale' ];
		$this->getScriptDomains              = $getScriptDomains ?: function ( $code ) {
			return self::getScriptDomainsWithTranslations( $code );
		};
	}

	public function add_hooks() {
		add_action( 'wpml_update_active_languages', [ $this, 'queue' ] );
		add_action( 'admin_init', [ $this, 'checkLocales' ] );
	}

	public function queue() {
		if ( ! has_action( 'shutdown', [ $this, 'processQueue' ] ) ) {
			add_action( 'shutdown', [ $this, 'processQueue' ] );
		}
	}

	public function checkLocales() {
		$stored = get_option( self::OPTION, false );

		if ( ! is_array( $stored ) ) {
			$this->saveLocales( $this->readLocales() );
			return;
		}

		$current = $this->readLocales( false );
		if ( $this->movedLocales( $stored, $current ) ) {
			$this->queue();
		} elseif ( array_diff_key( $current, $stored ) ) {
			$this->saveLocales( array_merge( $current, $stored ) );
		}
	}

	public function processQueue() {
		remove_action( 'shutdown', [ $this, 'processQueue' ] );

		$stored  = get_option( self::OPTION, false );
		$current = $this->readLocales();

		if ( is_array( $stored ) ) {
			$moved = $this->movedLocales( $stored, $current );
			if ( $moved && ! call_user_func( $this->canWriteFiles ) ) {
				$current = array_merge( $current, array_intersect_key( $stored, $moved ) );
			} else {
				foreach ( $moved as $code => $locale ) {
					call_user_func( $this->copyPluralForms, (string) $stored[ $code ], $locale );
				}
				$this->rebuild( $moved );
			}
		}

		$this->saveLocales( $current );
	}

	private function movedLocales( array $stored, array $current ) {
		$moved = [];
		foreach ( $current as $code => $locale ) {
			if (
				$locale
				&& array_key_exists( $code, $stored )
				&& (string) $stored[ $code ] !== (string) $locale
			) {
				$moved[ $code ] = (string) $locale;
			}
		}

		return $moved;
	}

	private function rebuild( array $moved ) {
		if ( ! $moved ) {
			return;
		}
		$locales = array_values( $moved );

		$this->domainsAndLanguagesRepository
			->get()
			->filter(
				function ( $pair ) use ( $locales ) {
					return in_array( (string) $pair->locale, $locales, true );
				}
			)
			->each(
				function ( $pair ) {
					try {
						$this->manager->add( $pair->domain, $pair->locale );
						if ( $this->scriptManager && $this->scriptManager->handles( $pair->domain ) ) {
							$this->scriptManager->add( $pair->domain, $pair->locale );
						}
					} catch ( \RuntimeException $e ) {
						return;
					}
				}
			);

		if ( ! $this->scriptManager ) {
			return;
		}
		foreach ( $moved as $code => $locale ) {
			foreach ( (array) call_user_func( $this->getScriptDomains, $code ) as $domain ) {
				try {
					$this->scriptManager->add( (string) $domain, $locale );
				} catch ( \RuntimeException $e ) {
					continue;
				}
			}
		}
	}

	private static function getScriptDomainsWithTranslations( $code ) {
		global $wpdb;

		$domains = make( Domains::class )->getJEDDomains()->values()->toArray();
		if ( ! $domains ) {
			return [];
		}

		return (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT s.context
				FROM {$wpdb->prefix}icl_strings s
				INNER JOIN {$wpdb->prefix}icl_string_translations t ON t.string_id = s.id
				WHERE t.language = %s
					AND ( ( t.status IN (%d, %d) AND t.value IS NOT NULL AND t.value <> '' ) OR ( t.mo_string IS NOT NULL AND t.mo_string <> '' ) )
					AND s.context IN (" . implode( ',', array_fill( 0, count( $domains ), '%s' ) ) . ')',
				array_merge( [ $code, ICL_TM_COMPLETE, ICL_TM_NEEDS_UPDATE ], $domains )
			)
		);
	}

	private function readLocales( $fresh = true ) {
		if ( $fresh ) {
			call_user_func( $this->resetLocaleCache );
		}

		return (array) call_user_func( $this->getLocales );
	}

	private function saveLocales( array $locales ) {
		update_option( self::OPTION, $locales, true );
	}
}
