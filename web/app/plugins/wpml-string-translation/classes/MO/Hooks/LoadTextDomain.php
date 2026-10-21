<?php

namespace WPML\ST\MO\Hooks;

use WPML\ST\MO\File\Manager;
use WPML\ST\MO\LoadedMODictionary;
use WPML_ST_Translations_File_Locale;
use function WPML\FP\partial;
use WPML\LIB\WP\WordPress;
use WPML\ST\MO\Hooks\LoadTranslationFile;

class LoadTextDomain implements \IWPML_Action {

	const PRIORITY_OVERRIDE = 10;

	private $file_manager;

	private $file_locale;

	private $loaded_mo_dictionary;

	private $loaded_domains = [];

	private $default_paths = [];

	private $existing_files = [];

	public function __construct(
		Manager $file_manager,
		WPML_ST_Translations_File_Locale $file_locale,
		LoadedMODictionary $loaded_mo_dictionary
	) {
		$this->file_manager         = $file_manager;
		$this->file_locale          = $file_locale;
		$this->loaded_mo_dictionary = $loaded_mo_dictionary;
	}

	public function add_hooks() {
		$this->reloadAlreadyLoadedMOFiles();

		add_filter( 'override_load_textdomain', [ $this, 'overrideLoadTextDomain' ], 10, 3 );
		add_filter( 'override_unload_textdomain', [ $this, 'overrideUnloadTextDomain' ], 10, 2 );
		add_action( 'wpml_language_has_switched', [ $this, 'languageHasSwitched' ] );
	}

	public function overrideLoadTextDomain( $override, $domain, $mofile ) {
		if ( ! $mofile ) {
			return $override;
		}


		if ( ! $this->isCustomMOLoaded( $domain ) ) {
			remove_filter( 'override_load_textdomain', [ $this, 'overrideLoadTextDomain' ], 10 );
			$locale      = $this->file_locale->get( $mofile, $domain );
			$wpml_mofile = $this->file_manager->get( $domain, $locale );
			$this->fallbackDefaultTranslations( $mofile, $domain, $locale, $wpml_mofile );
			$this->loadCustomMOFile( $domain, $mofile, $locale, $wpml_mofile );
			add_filter( 'override_load_textdomain', [ $this, 'overrideLoadTextDomain' ], 10, 3 );
		}

		$this->loaded_mo_dictionary->addFile( $domain, $mofile );

		return $override;
	}

	public function overrideUnloadTextDomain( $override, $domain ) {
		$key = array_search( $domain, $this->loaded_domains );

		if ( false !== $key ) {
			unset( $this->loaded_domains[ $key ] );
		}

		return $override;
	}

	private function isCustomMOLoaded( $domain ) {
		return in_array( $domain, $this->loaded_domains, true );
	}

	private function loadCustomMOFile( $domain, $mofile, $locale, $wpml_mofile = false ) {
		if ( false === $wpml_mofile ) {
			$wpml_mofile = $this->file_manager->get( $domain, $locale );
		}

		if ( $wpml_mofile && $wpml_mofile !== $mofile ) {
			$defaultTextdomainPath = $this->getDefaultWordPressTranslationPath( $domain, $locale );

			load_textdomain( $domain, $wpml_mofile );

			if ( $defaultTextdomainPath && ! self::isRequestedFile( $defaultTextdomainPath, $mofile ) ) {
				$this->loadWordPressJITMoFile( $defaultTextdomainPath, $domain );
			}
		}

		$this->setCustomMOLoaded( $domain );
	}

	private function loadWordPressJITMoFile( $path, $domain ) {
		load_textdomain( $domain, $path );
	}

	private function getDefaultWordPressTranslationPath( $domain, $locale ) {
		$key = $domain . '|' . $locale;
		if ( ! array_key_exists( $key, $this->default_paths ) ) {
			$this->default_paths[ $key ] = LoadTranslationFile::getDefaultWordPressTranslationPath( $domain, $locale ) ?: null;
		}

		return $this->default_paths[ $key ];
	}

	private function fileExists( $path ) {
		if ( ! isset( $this->existing_files[ $path ] ) ) {
			$this->existing_files[ $path ] = file_exists( $path );
		}

		return $this->existing_files[ $path ];
	}

	private static function isRequestedFile( $path, $mofile ) {
		return is_string( $mofile )
			&& '' !== $mofile
			&& ( $path === $mofile || $path === LoadTranslationFile::replaceMoExtensionWithPhp( $mofile ) );
	}

	private function reloadAlreadyLoadedMOFiles() {
		$this->loaded_mo_dictionary->getEntities()->each( function ( $entity ) {
			unload_textdomain( $entity->domain );
			list( $mofile, $locale ) = $this->getFileInLocale( $entity );
			$this->loadCustomMOFile( $entity->domain, $mofile, $locale );
			if ( class_exists( '\WP_Translation_Controller' ) ) {
				load_textdomain( $entity->domain, $mofile, $locale );
			} else {
				load_textdomain( $entity->domain, $mofile );
			}
		} );
	}

	private function getFileInLocale( $entity ) {
		$fileLocale   = $this->file_locale->get( $entity->mofile, $entity->domain );
		$loadedLocale = ! empty( $entity->locale ) ? $entity->locale : $fileLocale;

		if ( ! $loadedLocale ) {
			return [ $entity->mofile, $fileLocale ];
		}

		$requestLocale = determine_locale();

		if (
			! $requestLocale
			|| $loadedLocale === $requestLocale
			|| str_replace( LoadedMODictionary::LOCALE_PLACEHOLDER, $loadedLocale, $entity->mofile_pattern ) !== $entity->mofile
		) {
			return [ $entity->mofile, $fileLocale ];
		}

		return [
			str_replace( LoadedMODictionary::LOCALE_PLACEHOLDER, $requestLocale, $entity->mofile_pattern ),
			$requestLocale,
		];
	}

	private function setCustomMOLoaded( $domain ) {
		$this->loaded_domains[] = $domain;
	}

	public function languageHasSwitched() {
		$this->loaded_domains = [];
	}

	public function fallbackDefaultTranslations( $mofile, $domain, $locale, $wpml_mofile = false ) {
		if (WordPress::versionCompare('>', '6.6.999') && is_string( $mofile )) {
			if ( false === $wpml_mofile ) {
				$wpml_mofile = $this->file_manager->get( $domain, $locale );
			}

			if ( ! $wpml_mofile ) {
				return;
			}

			if ( $wpml_mofile !== $mofile ) {
				$defaultTextdomainPath = $this->getDefaultWordPressTranslationPath( $domain, $locale );
				if ( $defaultTextdomainPath && self::isRequestedFile( $defaultTextdomainPath, $mofile ) ) {
					return;
				}
			}

			$replaced_mofile = LoadTranslationFile::replaceMoExtensionWithPhp( $mofile );
			if ( ! $this->fileExists( $mofile ) && ! $this->fileExists( $replaced_mofile ) ) {
				$defaultTranslationsFile = $this->getDefaultWordPressTranslationPath( $domain, $locale ) ?: $wpml_mofile;
				LoadTranslationFile::replaceTranslationFile($domain, $mofile, $defaultTranslationsFile);
			}
		}
	}
}
