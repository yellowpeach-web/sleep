<?php

namespace WPML\WPSEO\RankMathSEO\AuthorBase;

class Hooks implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	const OPTION_NAME = 'rank-math-options-titles';
	const SETTING_KEY = 'url_author_base';

	const RANK_MATH_CHANGE_AUTHOR_BASE = 'RankMath\\Rewrite::change_author_base';

	private $originalBase;

	private $bases = [];

	private $translatedRules = [];

	public function add_hooks() {
		add_filter( 'rank_math/author_base', [ $this, 'translateBase' ] );
		add_action( 'wpml_language_has_switched', [ $this, 'switchGlobalBase' ] );
		add_filter( 'author_rewrite_rules', [ $this, 'storeOriginalBaseRules' ] );
		add_filter( 'option_rewrite_rules', [ $this, 'addTranslatedBaseRules' ] );
		add_filter( 'icl_ls_languages', [ $this, 'translateLanguageSwitcherUrls' ] );
	}

	public function translateBase( $base ) {
		$originalBase = $this->getOriginalBase();

		if ( ! $originalBase || $base !== $originalBase ) {
			return $base;
		}

		return $this->getBase( $this->getCurrentLanguage() );
	}

	public function switchGlobalBase() {
		global $wp_rewrite;

		$originalBase = $this->getOriginalBase();

		if ( ! $originalBase || ! $wp_rewrite || ! has_action( 'init', self::RANK_MATH_CHANGE_AUTHOR_BASE ) ) {
			return;
		}

		$base = apply_filters( 'rank_math/author_base', $originalBase );

		if ( $base ) {
			$wp_rewrite->author_base      = $base;
			$wp_rewrite->author_structure = '/' . $base . '/%author%';
		}
	}

	public function storeOriginalBaseRules( $rules ) {
		global $wp_rewrite;

		$originalBase = $this->getOriginalBase();

		if ( ! is_array( $rules ) || ! $originalBase || ! $wp_rewrite || $wp_rewrite->author_base === $originalBase ) {
			return $rules;
		}

		$currentBase = (string) $wp_rewrite->author_base;
		$stored      = [];

		foreach ( $rules as $regex => $query ) {
			$stored[ $this->replaceBase( $regex, $currentBase, $originalBase ) ] = $query;
		}

		return $stored;
	}

	public function addTranslatedBaseRules( $rules ) {
		$originalBase = $this->getOriginalBase();

		if ( empty( $rules ) || ! is_array( $rules ) || ! $originalBase ) {
			return $rules;
		}

		$language = $this->getCurrentLanguage();
		$base     = $this->getBase( $language );

		if ( $base === $originalBase ) {
			return $rules;
		}

		if ( isset( $this->translatedRules[ $language ] ) && $this->translatedRules[ $language ]['source'] === $rules ) {
			return $this->translatedRules[ $language ]['rules'];
		}

		$translated = [];

		foreach ( $rules as $regex => $query ) {
			if ( $this->isAuthorRule( $regex, $query, $originalBase ) ) {
				$translated[ $this->replaceBase( $regex, $originalBase, $base ) ] = $query;
			}

			$translated[ $regex ] = $query;
		}

		$this->translatedRules[ $language ] = [
			'source' => $rules,
			'rules'  => $translated,
		];

		return $translated;
	}

	public function translateLanguageSwitcherUrls( $languages ) {
		if ( ! is_array( $languages ) || ! is_author() || ! $this->getOriginalBase() ) {
			return $languages;
		}

		$author = get_queried_object();

		if ( ! $author instanceof \WP_User ) {
			return $languages;
		}

		$currentBase = $this->getBase( $this->getCurrentLanguage() );

		foreach ( $languages as $languageCode => $language ) {
			if ( isset( $language['url'] ) && is_string( $language['url'] ) ) {
				$languages[ $languageCode ]['url'] = $this->replaceBaseInUrl(
					$language['url'],
					$author->user_nicename,
					$currentBase,
					$this->getBase( $languageCode )
				);
			}
		}

		return $languages;
	}

	private function getBase( string $language ): string {
		if ( ! isset( $this->bases[ $language ] ) ) {
			$translatedValue = apply_filters(
				'wpml_translate_single_string',
				$this->getOriginalValue(),
				'admin_texts_' . self::OPTION_NAME,
				'[' . self::OPTION_NAME . ']' . self::SETTING_KEY,
				$language
			);

			$this->bases[ $language ] = $this->sanitize( $translatedValue ) ?: $this->getOriginalBase();
		}

		return $this->bases[ $language ];
	}

	private function getOriginalBase(): string {
		if ( null === $this->originalBase ) {
			$this->originalBase = $this->sanitize( $this->getOriginalValue() );
		}

		return $this->originalBase;
	}

	private function getOriginalValue(): string {
		$options = maybe_unserialize( apply_filters( 'wpml_unfiltered_admin_string', false, self::OPTION_NAME ) );

		$value = is_array( $options ) ? ( $options[ self::SETTING_KEY ] ?? '' ) : '';

		return is_string( $value ) ? $value : '';
	}

	private function sanitize( $value ): string {
		return is_string( $value ) && '' !== $value ? sanitize_title_with_dashes( $value, '', 'save' ) : '';
	}

	private function isAuthorRule( string $regex, string $query, string $base ): bool {
		return 0 === strpos( $regex, $base . '/' ) && false !== strpos( $query, 'author_name=' );
	}

	private function replaceBase( string $regex, string $from, string $to ): string {
		return 0 === strpos( $regex, $from . '/' ) ? $to . substr( $regex, strlen( $from ) ) : $regex;
	}

	private function replaceBaseInUrl( string $url, string $authorSlug, string $from, string $to ): string {
		if ( $from === $to ) {
			return $url;
		}

		$pathLength = strcspn( $url, '?#' );
		$path       = substr( $url, 0, $pathLength );
		$search     = '/' . $from . '/' . $authorSlug;
		$position   = strrpos( $path, $search );

		if ( false === $position ) {
			return $url;
		}

		$path = substr_replace( $path, '/' . $to . '/' . $authorSlug, $position, strlen( $search ) );

		return $path . substr( $url, $pathLength );
	}

	private function getCurrentLanguage(): string {
		return (string) apply_filters( 'wpml_current_language', null );
	}
}
