<?php

namespace WPML\WPSEO\Shared\Sitemap;

class ShopUrls {

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function getByLanguage( callable $isIndexablePost ): array {
		$shopPageId = (int) wc_get_page_id( 'shop' );

		if ( $shopPageId <= 0 ) {
			return [];
		}

		$shopUrls = [];

		foreach ( array_keys( $this->sitepress->get_active_languages() ) as $language ) {
			$translatedShopPageId = apply_filters( 'wpml_object_id', $shopPageId, 'page', false, $language );

			if ( $translatedShopPageId && $isIndexablePost( $translatedShopPageId ) ) {
				$shopUrls[ $language ] = $this->getPermalinkInLanguage( (int) $translatedShopPageId, $language );
			}
		}

		return array_filter( $shopUrls );
	}

	private function getPermalinkInLanguage( int $pageId, string $language ) {
		$this->sitepress->switch_lang( $language );

		try {
			return get_permalink( $pageId );
		} finally {
			$this->sitepress->switch_lang();
		}
	}
}
