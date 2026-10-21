<?php

namespace WPML\WPSEO\RankMathSEO\Sitemap\LangMode;

use WPML\WPSEO\RankMathSEO\Sitemap\PostTranslationsJoin;

class DomainHooks implements \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const RANK_MATH_ORDER = 'ORDER BY p.post_modified DESC LIMIT';

	private $sitepress;

	private $wpdb;

	public function __construct( \SitePress $sitepress, \wpdb $wpdb ) {
		$this->sitepress = $sitepress;
		$this->wpdb      = $wpdb;
	}

	public function add_hooks() {
		add_filter( 'rank_math/sitemap/entry', [ $this, 'excludeOtherLanguages' ], 10, 3 );
		add_filter( 'rank_math/sitemap/post_count/join', [ $this, 'joinLanguage' ], 10, 2 );
		add_filter( 'rank_math/sitemap/post_count/where', [ $this, 'filterByCurrentLanguage' ], 10, 2 );
		add_filter( 'rank_math/sitemap/get_posts/join', [ $this, 'joinLanguageToPosts' ], 10, 2 );
		add_filter( 'rank_math/sitemap/get_posts/where', [ $this, 'filterByCurrentLanguage' ], 10, 2 );
	}

	public function excludeOtherLanguages( $url, $type, $element ) {
		if ( ! $this->isXmlSitemapRequest() ) {
			return $url;
		}

		if ( 'post' === $type ) {
			$elementType = 'post_' . $element->post_type;
			$elementId   = $element->ID;
		} elseif ( 'term' === $type ) {
			$elementType = 'tax_' . $element->taxonomy;
			$elementId   = $element->term_id;
		} else {
			return $url;
		}

		$currentLanguage = $this->sitepress->get_current_language();
		$elementLanguage = $this->sitepress->get_element_language_details( $elementId, $elementType );

		if ( $elementLanguage && $currentLanguage !== $elementLanguage->language_code ) {
			return '';
		}

		return $url;
	}

	public function joinLanguage( $sql, $postType ) {
		if ( ! $this->isTranslatedPostTypeInXmlSitemap( $postType ) ) {
			return $sql;
		}

		return $sql . PostTranslationsJoin::sql( $this->wpdb );
	}

	public function joinLanguageToPosts( $sql, $postType ) {
		if ( $this->isTranslatedPostTypeInXmlSitemap( $postType ) ) {
			add_filter( 'query', [ $this, 'addIdToOrder' ] );
		}

		return $this->joinLanguage( $sql, $postType );
	}

	public function addIdToOrder( $query ) {
		if ( ! is_string( $query ) || false === strpos( $query, self::RANK_MATH_ORDER ) ) {
			return $query;
		}

		remove_filter( 'query', [ $this, 'addIdToOrder' ] );

		return str_replace( self::RANK_MATH_ORDER, 'ORDER BY p.post_modified DESC, p.ID DESC LIMIT', $query );
	}

	public function filterByCurrentLanguage( $sql, $postType ) {
		if ( ! $this->isTranslatedPostTypeInXmlSitemap( $postType ) ) {
			return $sql;
		}

		return $sql . $this->wpdb->prepare( ' AND wpml_translations.language_code = %s', $this->sitepress->get_current_language() );
	}

	private function isTranslatedPostTypeInXmlSitemap( $postType ): bool {
		return $this->isXmlSitemapRequest()
			&& is_string( $postType )
			&& $this->sitepress->is_translated_post_type( $postType );
	}

	private function isXmlSitemapRequest() {
		return (bool) get_query_var( 'sitemap' );
	}
}
