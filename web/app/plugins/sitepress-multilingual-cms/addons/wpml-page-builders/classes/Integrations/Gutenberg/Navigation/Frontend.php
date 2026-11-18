<?php

namespace WPML\PB\Gutenberg\Navigation;

use WPML\Convert\Ids;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\PB\Gutenberg\ConvertIdsInBlock\Prefetch;

class Frontend implements \WPML\PB\Gutenberg\Integration {

	const KIND_POST_TYPE = 'post-type';
	const KIND_TAXONOMY  = 'taxonomy';

	public function add_hooks() {
		add_filter( 'render_block_data', [ $this, 'translateNavigationId' ] );
		add_filter( 'block_core_navigation_render_inner_blocks', [ $this, 'translateNavigationLinkId' ] );
	}

	public function translateNavigationId( $data ) {
		if ( Relation::propEq( 'blockName', 'core/navigation', $data ) && Obj::path( [ 'attrs', 'ref' ], $data ) ) {
			$data['attrs']['ref'] = apply_filters( 'wpml_object_id', $data['attrs']['ref'], 'wp_navigation', true );

		}

		return $data;
	}

	public function translateNavigationLinkId( $blocks ) {
		$links = $this->collectLinks( $blocks );
		$this->prefetch( $links );

		foreach ( $links as $block ) {
			if ( self::KIND_POST_TYPE === Obj::prop( 'kind', $block->attributes ) ) {
				$block->attributes['id'] = Ids::convert( $block->attributes['id'], Ids::ANY_POST, true );
			} else {
				$block->attributes['id'] = Ids::convert( $block->attributes['id'], Ids::ANY_TERM, true );
			}
		}

		return $blocks;
	}

	private function collectLinks( $blocks ): array {
		$links = [];
		foreach ( $blocks as $block ) {
			if ( Obj::prop( 'id', $block->attributes ) && in_array( Obj::prop( 'kind', $block->attributes ), [ self::KIND_POST_TYPE, self::KIND_TAXONOMY ], true ) ) {
				$links[] = $block;
			}
			$links = array_merge( $links, $this->collectLinks( $block->inner_blocks ) );
		}

		return $links;
	}

	private function prefetch( array $links ) {
		$postIds = [];
		$termIds = [];
		foreach ( $links as $block ) {
			if ( self::KIND_POST_TYPE === Obj::prop( 'kind', $block->attributes ) ) {
				$postIds[] = (int) $block->attributes['id'];
			} else {
				$termIds[] = (int) $block->attributes['id'];
			}
		}

		$postIds = array_values( array_unique( array_filter( $postIds ) ) );
		$termIds = array_values( array_unique( array_filter( $termIds ) ) );
		if ( $postIds ) {
			_prime_post_caches( $postIds, false, false );
		}
		if ( $termIds ) {
			_prime_term_caches( $termIds, false );
		}
		if ( $postIds || $termIds ) {
			Prefetch::ids( [ Ids::ANY_POST => $postIds, Ids::ANY_TERM => $termIds ] );
		}
	}

}
