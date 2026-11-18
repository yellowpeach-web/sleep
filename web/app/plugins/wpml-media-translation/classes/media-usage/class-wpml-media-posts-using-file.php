<?php

class WPML_Media_Posts_Using_File {

	public static function get( $attachment_id, array $media_usage_posts ) {
		$posts = $media_usage_posts;

		if ( class_exists( '\WPML\MediaTranslation\UsageOfMediaFilesInPosts' ) ) {
			$usage_in_posts = new \WPML\MediaTranslation\UsageOfMediaFilesInPosts();
			$posts          = array_merge(
				$posts,
				(array) $usage_in_posts->getUsagesAsCopy( $attachment_id ),
				(array) $usage_in_posts->getUsagesAsReference( $attachment_id )
			);
		}

		$post_ids = array();
		foreach ( $posts as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id > 0 ) {
				$post_ids[ $post_id ] = $post_id;
			}
		}

		return array_values( $post_ids );
	}
}
