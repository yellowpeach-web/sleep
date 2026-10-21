<?php

namespace WPML\ST\MO\JustInTime;

use NOOP_Translations;
use WPML\LIB\WP\WordPress;
use WPML\ST\MO\Hooks\LoadTranslationFile;
use WPML\ST\MO\LoadedMODictionary;
use WPML\ST\TranslationFile\PluralForms;

class MO extends \MO {

	private $loaded_mo_dictionary;

	protected $locale;

	private $domain;

	private $isLoading = false;

	public function __construct(
		LoadedMODictionary $loaded_mo_dictionary,
		$locale,
		$domain
	) {
		$this->loaded_mo_dictionary = $loaded_mo_dictionary;
		$this->locale               = $locale;
		$this->domain               = $domain;
	}

	public function translate( $singular, $context = null ) {
		if ( $this->isLoading ) {
			return $singular;
		}

		$this->load();

		if ( null === $context ) {
			return __( $singular, $this->domain );
		}

		return _x( $singular, $context, $this->domain );
	}

	public function translate_plural( $singular, $plural, $count, $context = null ) {
		if ( $this->isLoading ) {
			return PluralForms::selectOriginal( $singular, $plural, $count );
		}

		$this->load();

		if ( null === $context ) {
			return _n( $singular, $plural, $count, $this->domain );
		}

		return _nx( $singular, $plural, $count, $context, $this->domain );
	}

	private function load() {
		if ( $this->isLoaded() ) {
			return true;
		}

		$this->isLoading = true;
		$controllerLocale = self::getTranslationControllerLocale();
		try {
			$this->loadTextDomain();
		} finally {
			self::restoreTranslationControllerLocale( $controllerLocale );
		}

		if ( ! $this->isLoaded() ) {
			$GLOBALS['l10n'][ $this->domain ] = new NOOP_Translations();
		}

		$this->isLoading = false;
	}

	private static function getTranslationControllerLocale() {
		return class_exists( \WP_Translation_Controller::class )
			? \WP_Translation_Controller::get_instance()->get_locale()
			: null;
	}

	private static function restoreTranslationControllerLocale( $locale ) {
		if ( ! $locale ) {
			return;
		}

		$controller = \WP_Translation_Controller::get_instance();
		if ( $controller->get_locale() !== $locale ) {
			$controller->set_locale( $locale );
		}
	}

	protected function loadTextDomain() {
		$mofiles = $this->loaded_mo_dictionary
			->getFiles( $this->domain, $this->locale )
			->toArray();

		if ( count( $mofiles ) === 0 ) {
			return;
		}

		$defaultTranslationPath =
			LoadTranslationFile::getDefaultWordPressTranslationPath( $this->domain, $this->locale );

		$files = [ reset( $mofiles ) ];
		if ( $defaultTranslationPath ) {
			$files[] = $defaultTranslationPath;
		}
		$files = array_unique( array_merge( $files, $mofiles ) );

		foreach ( $files as $mofile ) {
			load_textdomain( $this->domain, $mofile, $this->locale );
		}
	}

	private function isLoaded() {
		return isset( $GLOBALS['l10n'][ $this->domain ] )
		       && ! $GLOBALS['l10n'][ $this->domain ] instanceof self;
	}
}
