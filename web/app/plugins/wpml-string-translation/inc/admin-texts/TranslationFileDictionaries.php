<?php

namespace WPML\ST\AdminTexts;

use WPML\ST\MO\File\ManagerFactory;
use WPML\ST\TranslationFile\Manager;

class TranslationFileDictionaries {

	const NO_FILE           = 'no-file';
	const USE_STRING_LOOKUP = 'use-string-lookup';

	private $manager;

	private $dictionaries = [];

	private $hooked = false;

	public function __construct( ?Manager $manager = null ) {
		$this->manager = $manager;
	}

	public function get( $domain, $locale ) {
		$this->addHooks();

		$key = get_current_blog_id() . '|' . $domain . '|' . $locale;
		if ( ! array_key_exists( $key, $this->dictionaries ) ) {
			$this->dictionaries[ $key ] = $this->read( $domain, $locale );
		}

		return $this->dictionaries[ $key ];
	}

	public function forget( $filepath, $domain, $locale ) {
		$suffix = '|' . $domain . '|' . $locale;
		foreach ( array_keys( $this->dictionaries ) as $key ) {
			if ( substr( $key, -strlen( $suffix ) ) === $suffix ) {
				unset( $this->dictionaries[ $key ] );
			}
		}
	}

	private function addHooks() {
		if ( $this->hooked ) {
			return;
		}
		$this->hooked = true;

		add_action( 'wpml_st_translation_file_written', [ $this, 'forget' ], 10, 3 );
		add_action( 'wpml_st_translation_file_removed', [ $this, 'forget' ], 10, 3 );
	}

	private function read( $domain, $locale ) {
		$mofile = $this->manager()->get( $domain, $locale );
		if ( ! $mofile ) {
			return ( defined( 'WPML_CHECK_MISSING_MO_FILES' ) && true === WPML_CHECK_MISSING_MO_FILES )
				? self::USE_STRING_LOOKUP
				: self::NO_FILE;
		}

		if ( apply_filters( 'load_textdomain_mofile', $mofile, $domain ) !== $mofile || '.mo' !== substr( $mofile, -3 ) ) {
			return self::USE_STRING_LOOKUP;
		}
		if ( 'mo' === apply_filters( 'translation_file_format', 'php', $domain ) ) {
			return self::USE_STRING_LOOKUP;
		}

		$file = substr( $mofile, 0, -3 ) . '.l10n.php';
		if ( apply_filters( 'load_translation_file', $file, $domain, $locale ) !== $file || ! is_readable( $file ) ) {
			return self::USE_STRING_LOOKUP;
		}

		try {
			$data = include $file;
		} catch ( \Throwable $e ) {
			return self::USE_STRING_LOOKUP;
		}

		return is_array( $data ) && isset( $data['messages'] ) && is_array( $data['messages'] )
			? $data['messages']
			: self::USE_STRING_LOOKUP;
	}

	private function manager() {
		if ( ! $this->manager ) {
			$this->manager = ManagerFactory::create();
		}

		return $this->manager;
	}
}
