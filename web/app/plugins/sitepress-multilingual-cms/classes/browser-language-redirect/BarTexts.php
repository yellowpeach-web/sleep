<?php

namespace WPML\BrowserLanguageRedirect;

use WPML\Languages\HreflangTag;

class BarTexts {

	const OPTION = 'wpml_language_bar_texts';

	const STRING_DOMAIN = 'WPML';

	const SOURCES = [
		'question' => 'View this page in %s?',
		'go'       => 'View in %s',
		'close'    => 'Close',
		'label'    => 'Language suggestion',
	];

	const STRING_NAMES = [
		'question' => 'Language suggestion bar - question',
		'go'       => 'Language suggestion bar - button',
		'close'    => 'Language suggestion bar - close button',
		'label'    => 'Language suggestion bar - region name',
	];

	const NAMED_TEXTS = [ 'question', 'go' ];

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function get() {
		$languages = $this->get_visible_languages();
		$texts     = $this->get_stored_texts( $this->get_all_active_languages() );

		$bar = [
			'texts' => [],
			'dir'   => [],
			'tags'  => [],
		];

		foreach ( $languages as $code => $language ) {
			$language_texts = isset( $texts[ $code ] ) ? $texts[ $code ] + self::SOURCES : self::SOURCES;
			$name           = $this->get_own_name( $code, $language );

			foreach ( self::NAMED_TEXTS as $key ) {
				$language_texts[ $key ] = self::insert_name( $language_texts[ $key ], $name );
			}

			$bar['texts'][ $code ] = [
				'question' => (string) $language_texts['question'],
				'go'       => (string) $language_texts['go'],
				'close'    => (string) $language_texts['close'],
				'label'    => (string) $language_texts['label'],
			];
			$bar['dir'][ $code ]   = $this->sitepress->is_rtl( $code ) ? 'rtl' : 'ltr';
			$bar['tags'][ $code ] = HreflangTag::forLanguage( $language );
		}

		return $bar;
	}

	public function refresh() {
		$languages = $this->get_all_active_languages();
		$this->build_and_store( $languages, $this->get_key( $languages ) );
	}

	public static function drop() {
		delete_option( self::OPTION );
	}

	public static function on_string_translation_saved( $string_translation_id, $translation_data = null, $language = null, $string_id = null ) {
		$stored = get_option( self::OPTION );
		if ( ! is_array( $stored ) ) {
			return;
		}

		$ids = isset( $stored['stringIds'] ) && is_array( $stored['stringIds'] )
			? array_filter( array_map( 'intval', $stored['stringIds'] ) )
			: [];

		if ( ! $ids || in_array( (int) $string_id, $ids, true ) ) {
			delete_option( self::OPTION );
		}
	}

	private function get_visible_languages() {
		return self::by_code( $this->sitepress->get_active_languages() );
	}

	private function get_all_active_languages() {
		return self::by_code( $this->sitepress->get_languages( false, true ) );
	}

	private static function by_code( $languages ) {
		if ( ! is_array( $languages ) ) {
			return [];
		}

		$by_code = [];
		foreach ( $languages as $code => $language ) {
			$by_code[ (string) $code ] = is_array( $language ) ? $language : [];
		}

		return $by_code;
	}

	private function get_stored_texts( array $languages ) {
		$key    = $this->get_key( $languages );
		$stored = get_option( self::OPTION );

		if ( is_array( $stored ) && isset( $stored['key'], $stored['texts'] ) && $key === $stored['key'] && is_array( $stored['texts'] ) ) {
			return $stored['texts'];
		}

		return $this->build_and_store( $languages, $key );
	}

	private function get_key( array $languages ) {
		$parts = [];
		foreach ( array_keys( $languages ) as $code ) {
			$parts[ $code ] = $code . ':' . (string) $this->sitepress->get_locale( $code );
		}
		ksort( $parts );

		$parts[] = ICL_SITEPRESS_VERSION;
		$parts[] = defined( 'WPML_ST_VERSION' ) ? WPML_ST_VERSION : '';

		return md5( implode( '|', $parts ) );
	}

	private function build_and_store( array $languages, $key ) {
		$this->register_strings();

		$texts = [];
		foreach ( array_keys( $languages ) as $code ) {
			$texts[ $code ] = $this->build_texts( $code );
		}

		update_option(
			self::OPTION,
			[
				'key'       => $key,
				'stringIds' => $this->get_string_ids(),
				'texts'     => $texts,
			],
			true
		);

		return $texts;
	}

	private function register_strings() {
		foreach ( self::SOURCES as $key => $source ) {
			do_action( 'wpml_register_single_string', self::STRING_DOMAIN, self::STRING_NAMES[ $key ], $source );
		}
	}

	private function get_string_ids() {
		$ids = [];
		foreach ( self::STRING_NAMES as $name ) {
			$id = (int) apply_filters(
				'wpml_string_id',
				null,
				[
					'context' => self::STRING_DOMAIN,
					'name'    => $name,
				]
			);
			if ( $id ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	private function build_texts( $code ) {
		$mo_texts = $this->get_mo_texts( $code );

		$texts = [];
		foreach ( self::SOURCES as $key => $source ) {
			$translation   = apply_filters( 'wpml_translate_single_string', $source, self::STRING_DOMAIN, self::STRING_NAMES[ $key ], $code );
			$texts[ $key ] = is_string( $translation ) && '' !== trim( $translation ) && $source !== $translation
				? $translation
				: $mo_texts[ $key ];
		}

		return $texts;
	}

	private function get_mo_texts( $code ) {
		$this->sitepress->switch_locale( $code );

		try {
			$texts = [
				/* translators: Text of a bar that suggests the page in the visitor's browser language. %s: the name of that language, written in that language, for example "español". */
				'question' => __( 'View this page in %s?', 'sitepress' ),
				/* translators: Button in a bar that suggests the page in the visitor's browser language; it opens the page in that language. %s: the name of that language, written in that language, for example "español". */
				'go'       => __( 'View in %s', 'sitepress' ),
				/* translators: Label of the button or control that closes something: a message or a bar on a page of the site, an open section, or a dialog. Verb, imperative, not the adjective meaning near. */
				'close'    => __( 'Close', 'sitepress' ),
				/* translators: Accessible name of the bar that suggests the page in the visitor's browser language, read by screen readers. */
				'label'    => __( 'Language suggestion', 'sitepress' ),
			];
		} finally {
			$this->sitepress->switch_locale();
		}

		return $texts;
	}

	private function get_own_name( $code, array $language ) {
		$name = $this->sitepress->get_display_language_name( $code, $code );
		if ( is_string( $name ) && '' !== $name ) {
			return $name;
		}

		return isset( $language['native_name'] ) && '' !== (string) $language['native_name'] ? (string) $language['native_name'] : $code;
	}

	private static function insert_name( $text, $name ) {
		return strtr(
			(string) $text,
			[
				'%1$s' => $name,
				'%s'   => $name,
			]
		);
	}
}
