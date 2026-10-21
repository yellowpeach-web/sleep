<?php

namespace WPML\QueryFiltering;

class ProductVariationTranslationPrefetcher implements \IWPML_Action, \IWPML_Frontend_Action_Loader, \IWPML_Backend_Action_Loader {

	const PRODUCT_POST_TYPE   = 'product';
	const VARIATION_POST_TYPE = 'product_variation';

	private $post_translations;

	private $wpdb;

	private $pending_products = [];

	public function __construct( ?\WPML_Element_Translation $postTranslations = null, ?\wpdb $wpdb = null ) {
		$this->post_translations = $postTranslations;
		$this->wpdb              = $wpdb;
	}

	public function create() {
		return $this;
	}

	public function add_hooks(): void {
		add_filter( 'the_posts', [ $this, 'noteProductsOfResultSet' ], 11, 1 );
		add_filter( 'woocommerce_get_children', [ $this, 'prefetchChildren' ], 10, 2 );
	}

	public function noteProductsOfResultSet( $posts ) {
		if ( ! is_array( $posts ) ) {
			return $posts;
		}
		foreach ( $posts as $post ) {
			if ( is_object( $post ) && isset( $post->ID, $post->post_type ) && self::PRODUCT_POST_TYPE === $post->post_type ) {
				$this->pending_products[ (int) $post->ID ] = true;
			}
		}

		return $posts;
	}

	public function prefetchChildren( $children, $product = null ) {
		$post_translations = $this->postTranslations();
		if ( ! $post_translations || ! is_array( $children ) ) {
			return $children;
		}

		$ids        = array_filter( array_map( 'intval', $children ) );
		$product_id = is_object( $product ) && method_exists( $product, 'get_id' ) ? (int) $product->get_id() : 0;

		if ( $product_id && isset( $this->pending_products[ $product_id ] ) ) {
			$products               = array_keys( $this->pending_products );
			$this->pending_products = [];
			if ( count( $products ) > 1 ) {
				$ids = array_merge( $ids, $this->variationIdsOf( $products ) );
			}
		}

		if ( $ids ) {
			$post_translations->prefetch_ids( array_values( array_unique( $ids ) ) );
		}

		return $children;
	}

	private function variationIdsOf( array $product_ids ) {
		$wpdb = $this->wpdb();
		if ( ! $wpdb ) {
			return [];
		}

		$product_ids = array_map( 'intval', $product_ids );

		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_parent IN (" . implode( ',', array_fill( 0, count( $product_ids ), '%d' ) ) . ')',
					array_merge( [ self::VARIATION_POST_TYPE ], $product_ids )
				)
			)
		);
	}

	private function postTranslations() {
		if ( $this->post_translations ) {
			return $this->post_translations;
		}
		global $wpml_post_translations;

		return $wpml_post_translations;
	}

	private function wpdb() {
		if ( $this->wpdb ) {
			return $this->wpdb;
		}
		global $wpdb;

		return $wpdb;
	}
}
