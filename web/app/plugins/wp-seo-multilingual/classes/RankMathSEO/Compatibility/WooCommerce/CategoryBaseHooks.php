<?php

namespace WPML\WPSEO\RankMathSEO\Compatibility\WooCommerce;

use WPML\FP\Obj;
use WPML\WPSEO\RankMathSEO\Utils;

class CategoryBaseHooks implements \IWPML_Frontend_Action, \IWPML_Backend_Action, \IWPML_REST_Action {

	const PRIORITY_AFTER_WPML_LANGUAGE_PREFIX = 2;

	const REMOVE_PARENT_SLUGS_KEY = 'wc_remove_category_parent_slugs';

	const REMOVE_CATEGORY_BASE_KEY = 'wc_remove_category_base';

	public function add_hooks() {
		if ( Utils::isGeneralSettingOn( self::REMOVE_PARENT_SLUGS_KEY ) ) {
			add_filter(
				'term_link',
				[ $this, 'restoreTranslatedCategoryBase' ],
				self::PRIORITY_AFTER_WPML_LANGUAGE_PREFIX,
				3
			);
		}

		if ( Utils::isGeneralSettingOn( self::REMOVE_CATEGORY_BASE_KEY ) ) {
			add_filter(
				'term_link',
				[ $this, 'removeTranslatedCategoryBase' ],
				self::PRIORITY_AFTER_WPML_LANGUAGE_PREFIX,
				3
			);
		}
	}

	public function restoreTranslatedCategoryBase( $link, $term, $taxonomy ) {
		$bases = $this->getBases( $term, $taxonomy );

		if ( ! $bases ) {
			return $link;
		}

		list( $base, $translatedBase ) = $bases;

		return $this->replaceBase( $link, $term->slug, $base, $translatedBase );
	}

	public function removeTranslatedCategoryBase( $link, $term, $taxonomy ) {
		$bases = $this->getBases( $term, $taxonomy );

		if ( ! $bases ) {
			return $link;
		}

		$translatedBase = $bases[1];

		if ( false === strpos( $link, '/' . $translatedBase . '/' ) ) {
			return $link;
		}

		foreach ( $this->getTermPaths( $term, $taxonomy ) as $termPath ) {
			$strippedLink = $this->replaceBase( $link, $termPath, $translatedBase, '' );

			if ( $strippedLink !== $link ) {
				return $strippedLink;
			}
		}

		return $link;
	}

	private function getBases( $term, string $taxonomy ): ?array {
		if ( 'product_cat' !== $taxonomy ) {
			return null;
		}

		$base = $this->getCategoryBase();

		if ( '' === $base ) {
			return null;
		}

		$language = $this->getTermLanguage( $term, $taxonomy );

		if ( ! $language ) {
			return null;
		}

		$translatedBase = $this->getTranslatedBase( $base, $taxonomy, $language );

		return $translatedBase === $base ? null : [ $base, $translatedBase ];
	}

	private function getTermPaths( $term, string $taxonomy ): array {
		$slugs = [ $term->slug ];

		foreach ( get_ancestors( $term->term_id, $taxonomy, 'taxonomy' ) as $ancestorId ) {
			$ancestorSlug = get_term_field( 'slug', $ancestorId, $taxonomy );

			if ( is_string( $ancestorSlug ) ) {
				array_unshift( $slugs, $ancestorSlug );
			}
		}

		return array_unique( [ implode( '/', $slugs ), $term->slug ] );
	}

	private function replaceBase( string $link, string $slug, string $base, string $newBase ): string {
		$pathLength = strcspn( $link, '?#' );
		$path       = substr( $link, 0, $pathLength );
		$search     = '/' . $base . '/' . $slug;
		$position   = strrpos( $path, $search );

		if ( false === $position ) {
			return $link;
		}

		$replace = '' === $newBase ? '/' . $slug : '/' . $newBase . '/' . $slug;
		$path    = substr_replace( $path, $replace, $position, strlen( $search ) );

		return $path . substr( $link, $pathLength );
	}

	private function getTermLanguage( $term, string $taxonomy ): ?string {
		$language = apply_filters(
			'wpml_element_language_code',
			null,
			[
				'element_id'   => $term->term_taxonomy_id,
				'element_type' => 'tax_' . $taxonomy,
			]
		);

		return $language ? (string) $language : null;
	}

	private function getTranslatedBase( string $base, string $taxonomy, string $language ): string {
		return (string) apply_filters(
			'wpml_translate_single_string',
			$base,
			'WordPress',
			sprintf( 'URL %s tax slug', $taxonomy ),
			$language
		);
	}

	private function getCategoryBase(): string {
		return trim( (string) Obj::prop( 'category_rewrite_slug', wc_get_permalink_structure() ), '/' );
	}
}
