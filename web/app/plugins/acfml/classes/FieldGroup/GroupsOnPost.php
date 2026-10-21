<?php

namespace ACFML\FieldGroup;

class GroupsOnPost {

	const ALL_ACF_BLOCKS = 'all';

	public static function filter( array $groups, int $postId ): array {
		if ( ! $groups ) {
			return [];
		}

		$onEditScreen = array_values( (array) acf_filter_field_groups( $groups, [ 'post_id' => $postId ] ) );

		$blockLocated = array_filter(
			$groups,
			fn( $group ) => ! in_array( $group, $onEditScreen, true ) && AttachedBlockPosts::blockNamesForGroup( $group )
		);

		$post = $blockLocated ? get_post( $postId ) : null;

		if ( ! $post ) {
			return $onEditScreen;
		}

		$inBlocks = array_filter( $blockLocated, fn( $group ) => self::isInBlockOf( $group, $post ) );

		return array_merge( $onEditScreen, array_values( $inBlocks ) );
	}

	private static function isInBlockOf( array $group, \WP_Post $post ): bool {
		foreach ( AttachedBlockPosts::blockNamesForGroup( $group ) as $blockName ) {
			$candidates = self::ALL_ACF_BLOCKS === $blockName ? array_keys( (array) acf_get_block_types() ) : [ $blockName ];

			foreach ( $candidates as $candidate ) {
				if ( has_block( $candidate, $post ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
