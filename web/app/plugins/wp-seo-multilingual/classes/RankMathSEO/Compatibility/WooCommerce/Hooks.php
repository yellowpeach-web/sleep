<?php

namespace WPML\WPSEO\RankMathSEO\Compatibility\WooCommerce;

use WPML\Element\API\PostTranslations;
use WPML\FP\Obj;
use WPML\WPSEO\RankMathSEO\Utils;
use function WPML\FP\pipe;

class Hooks implements \IWPML_Frontend_Action {

	private const PRODUCT_POST_TYPE = 'product';

	public function add_hooks() {
		add_filter( 'rank_math/frontend/breadcrumb/is_using_shop_base', [ $this, 'filterIsUsingShopBase' ] );
		add_filter( 'rank_math/sitemap/post_type_archive_link', [ $this, 'useShopPageOfCurrentLanguage' ], 11, 2 );
	}

	public function useShopPageOfCurrentLanguage( $archiveLink, $postType ) {
		if ( ! $archiveLink || self::PRODUCT_POST_TYPE !== $postType ) {
			return $archiveLink;
		}

		$shopPageId = apply_filters( 'wpml_object_id', wc_get_page_id( 'shop' ), 'page', false );

		return $shopPageId && Utils::isIndexablePost( $shopPageId ) ? get_permalink( $shopPageId ) : false;
	}

	public function filterIsUsingShopBase( $isUsingShopBase ) {
		if ( $isUsingShopBase ) {
			return $isUsingShopBase;
		}

		$defaultShopSlug = wpml_collect( PostTranslations::get( wc_get_page_id( 'shop' ) ) )
			->filter( Obj::prop( 'original' ) )
			->map( pipe( Obj::prop( 'element_id' ), 'get_post', Obj::prop( 'post_name' ) ) )
			->first();

		return (bool) strstr(
			(string) Obj::prop( 'product_base', wc_get_permalink_structure() ),
			'/' . $defaultShopSlug
		);
	}
}
