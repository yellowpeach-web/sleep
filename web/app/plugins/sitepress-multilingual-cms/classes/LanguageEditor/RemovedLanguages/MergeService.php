<?php

namespace WPML\LanguageEditor\RemovedLanguages;

class MergeService {

	public static function collisions( array $collidingRows ) {
		global $sitepress;
		$terms = 0;
		$posts = 0;

		$sitepress->switch_lang( 'all' );
		try {
			foreach ( $collidingRows as $row ) {
				if ( ! self::isCountedByThePanelRow( $row ) ) {
					continue;
				}
				if ( 0 === strpos( (string) $row->element_type, 'tax_' ) ) {
					$terms++;
				} elseif ( 0 === strpos( (string) $row->element_type, 'post_' ) ) {
					$posts++;
				}
			}
		} finally {
			$sitepress->switch_lang( null );
		}

		return array(
			'terms' => $terms,
			'posts' => $posts,
			'total' => $terms + $posts,
		);
	}

	private static function isCountedByThePanelRow( $row ) {
		$type = (string) $row->element_type;
		$id   = (int) $row->source_element_id;
		if ( 0 === strpos( $type, 'post_' ) ) {
			return 'post_' . (string) get_post_type( $id ) === $type;
		}
		if ( 0 === strpos( $type, 'tax_' ) ) {
			return (bool) get_term_by( 'term_taxonomy_id', $id, substr( $type, 4 ), OBJECT, 'raw' );
		}
		return false;
	}

	public static function run( array $collidingRows, $source, $target ) {
		$termsMerged              = 0;
		$postsDetached            = 0;
		$defaultCategoryForgotten = false;

		foreach ( $collidingRows as $row ) {
			$elementType = (string) $row->element_type;

			if ( 0 === strpos( $elementType, 'tax_' ) ) {
				$sourceTtId = (int) $row->source_element_id;
				self::mergeTerm( substr( $elementType, 4 ), $sourceTtId, (int) $row->target_element_id );
				$termsMerged++;

				if ( self::forgetDefaultCategoryIfDeleted( $source, $sourceTtId ) ) {
					$defaultCategoryForgotten = true;
				}
			} elseif ( 0 === strpos( $elementType, 'post_' ) ) {
				self::detachPost( (int) $row->source_element_id, substr( $elementType, 5 ), $target );
				$postsDetached++;
			}
		}

		return array(
			'termsMerged'              => $termsMerged,
			'postsDetached'            => $postsDetached,
			'defaultCategoryForgotten' => $defaultCategoryForgotten,
		);
	}

	private static function mergeTerm( $taxonomy, $sourceTtId, $targetTtId ) {
		global $wpdb, $sitepress;

		$sitepress->switch_lang( 'all' );

		try {
			$sourceTerm = get_term_by( 'term_taxonomy_id', $sourceTtId, $taxonomy, OBJECT, 'raw' );
			$targetTerm = get_term_by( 'term_taxonomy_id', $targetTtId, $taxonomy, OBJECT, 'raw' );

			if ( ! $sourceTerm || ! $targetTerm ) {
				return;
			}

			$sourceObjectIds = get_objects_in_term( (int) $sourceTerm->term_id, $taxonomy );
			$targetObjectIds = get_objects_in_term( (int) $targetTerm->term_id, $taxonomy );
			$sourceObjectIds = is_wp_error( $sourceObjectIds ) ? array() : array_map( 'intval', $sourceObjectIds );
			$targetObjectIds = is_wp_error( $targetObjectIds ) ? array() : array_map( 'intval', $targetObjectIds );

			$colliding = array_intersect( $sourceObjectIds, $targetObjectIds );
			$moving    = array_diff( $sourceObjectIds, $colliding );

			foreach ( $colliding as $objectId ) {
				$wpdb->delete(
					$wpdb->term_relationships,
					array(
						'object_id'        => $objectId,
						'term_taxonomy_id' => $sourceTtId,
					),
					array( '%d', '%d' )
				);
			}

			foreach ( $moving as $objectId ) {
				$wpdb->update(
					$wpdb->term_relationships,
					array( 'term_taxonomy_id' => $targetTtId ),
					array(
						'object_id'        => $objectId,
						'term_taxonomy_id' => $sourceTtId,
					),
					array( '%d' ),
					array( '%d', '%d' )
				);
			}

			wp_update_term_count_now( array( $targetTtId ), $taxonomy );

			foreach ( get_term_meta( (int) $sourceTerm->term_id ) as $key => $values ) {
				foreach ( $values as $value ) {
					add_term_meta( (int) $targetTerm->term_id, $key, maybe_unserialize( $value ) );
				}
			}

			wp_delete_term( (int) $sourceTerm->term_id, $taxonomy );
		} finally {
			$sitepress->switch_lang( null );
		}
	}

	private static function forgetDefaultCategoryIfDeleted( $source, $deletedTtId ) {
		global $sitepress;

		$defaultCategories = (array) $sitepress->get_setting( 'default_categories', array() );

		if ( ! isset( $defaultCategories[ $source ] ) || (int) $defaultCategories[ $source ] !== $deletedTtId ) {
			return false;
		}

		unset( $defaultCategories[ $source ] );
		$sitepress->set_default_categories( $defaultCategories );

		return true;
	}

	private static function detachPost( $postId, $postType, $target ) {
		global $sitepress;

		$sitepress->set_element_language_details( $postId, 'post_' . $postType, false, $target );
	}
}
