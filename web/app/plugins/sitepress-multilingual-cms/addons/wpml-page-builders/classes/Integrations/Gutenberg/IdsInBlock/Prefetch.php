<?php

namespace WPML\PB\Gutenberg\ConvertIdsInBlock;

use WPML\Convert\Ids;
use WPML\PB\Gutenberg\ReusableBlocks\Blocks;
use WPML\PB\Gutenberg\ReusableBlocks\Translation as ReusableBlockTranslation;

class Prefetch {

	public static function tree( array $block, $idsOfBlock = null ) {
		$bySlug = [];
		self::walk( $block, $idsOfBlock, $bySlug );
		self::bySlug( $bySlug );
	}

	public static function idsIn( $value ) {
		if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
			return [ (int) $value ];
		}

		if ( is_array( $value ) ) {
			$ids = [];
			foreach ( $value as $item ) {
				if ( is_int( $item ) || ( is_string( $item ) && ctype_digit( $item ) ) ) {
					$ids[] = (int) $item;
				}
			}

			return $ids;
		}

		if ( is_string( $value ) && preg_match( '/^[\d\s,;|"\[\]]+$/', $value ) && preg_match_all( '/\d+/', $value, $matches ) ) {
			return array_map( 'intval', $matches[0] );
		}

		return [];
	}

	private static function walk( $block, $idsOfBlock, array &$bySlug ) {
		if ( ! is_array( $block ) ) {
			return;
		}

		if ( isset( $block['blockName'] ) && Blocks::isReusable( $block ) ) {
			$bySlug[ ReusableBlockTranslation::POST_TYPE ][] = Blocks::getReusableId( $block );
		}

		if ( $idsOfBlock && isset( $block['blockName'] ) ) {
			foreach ( $idsOfBlock( $block ) as $slug => $ids ) {
				$bySlug[ $slug ] = array_merge( isset( $bySlug[ $slug ] ) ? $bySlug[ $slug ] : [], $ids );
			}
		}

		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $innerBlock ) {
				self::walk( $innerBlock, $idsOfBlock, $bySlug );
			}
		}
	}

	private static function bySlug( array $bySlug ) {
		global $wpml_post_translations, $wpml_term_translations;

		$postIds = [];
		$termIds = [];
		foreach ( $bySlug as $slug => $ids ) {
			if ( Ids::ANY_POST === $slug || post_type_exists( $slug ) ) {
				$postIds = array_merge( $postIds, $ids );
			} elseif ( Ids::ANY_TERM === $slug || taxonomy_exists( $slug ) ) {
				$termIds = array_merge( $termIds, $ids );
			}
		}

		$postIds = array_values( array_unique( array_filter( $postIds ) ) );
		if ( $postIds && $wpml_post_translations instanceof \WPML_Post_Translation ) {
			$wpml_post_translations->prefetch_ids( $postIds );
		}

		$termIds = array_values( array_unique( array_filter( $termIds ) ) );
		if ( $termIds && $wpml_term_translations instanceof \WPML_Term_Translation ) {
			$wpml_term_translations->prefetch_ids(
				array_values( array_unique( array_map( 'intval', array_map( [ $wpml_term_translations, 'adjust_ttid_for_term_id' ], $termIds ) ) ) )
			);
		}
	}
}
