<?php

namespace WPML\PB\Elementor\Config\DynamicElements;

use WPML\FP\Obj;
use WPML\PB\Elementor\Helper\Path;

class WooProduct {

	const DYNAMIC_KEYS = [
		'title_text',
		'description_text',
		'image',
		'link',
		'editor',
		'title',
		'text',
		'content',
		'url',
	];

	const TAG_NAMES = [
		'woocommerce-product-title-tag',
		'woocommerce-product-image-tag',
		'woocommerce-product-price-tag',
		'woocommerce-product-short-description-tag',
		'woocommerce-product-content-tag',
		'woocommerce-product-gallery-tag',
		'woocommerce-product-stock-tag',
		'woocommerce-product-rating-tag',
		'woocommerce-product-sale-tag',
		'woocommerce-product-terms-tag',
		'woocommerce-product-add-to-cart-tag',
		'woocommerce-product-sku-tag',
	];

	private static function getConverter( $dynamicKey ) {
		$dynamicPath = [ 'settings', '__dynamic__', $dynamicKey ];

		$hasDynamicTag = function ( $item ) use ( $dynamicPath ) {
			$value = Path::get( $dynamicPath, $item );

			return is_string( $value )
				&& strpos( $value, '[elementor-tag' ) !== false
				&& strpos( $value, 'name="woocommerce-product-' ) !== false
				&& self::hasTagName( $value );
		};

		$dynamicLens = Obj::lensPath( $dynamicPath );

		return [ $hasDynamicTag, $dynamicLens, self::TAG_NAMES, 'product_id' ];
	}

	private static function hasTagName( $value ) {
		foreach ( self::TAG_NAMES as $tagName ) {
			if ( strpos( $value, 'name="' . $tagName . '"' ) !== false ) {
				return true;
			}
		}

		return false;
	}

	public static function getAll() {
		$converters = [];

		foreach ( self::DYNAMIC_KEYS as $dynamicKey ) {
			$converters[] = self::getConverter( $dynamicKey );
		}

		return $converters;
	}
}
