<?php

namespace WPML\PB\Helper;

class TranslationContent {

	public static function isStored( $postId, $content ) {
		if ( ! is_string( $content ) || ! $postId ) {
			return false;
		}

		$post = get_post( (int) $postId );

		return is_object( $post ) && $post->post_content === $content;
	}

	public static function update( array $postarr, ...$args ) {
		$onlyContent = 2 === count( $postarr ) && isset( $postarr['ID'], $postarr['post_content'] );

		if ( $onlyContent && self::isStored( $postarr['ID'], $postarr['post_content'] ) ) {
			return false;
		}

		wpml_update_escaped_post( $postarr, ...$args );

		return true;
	}

	public static function followRecord( $postId ) {
		$post = $postId ? get_post( (int) $postId ) : null;

		if ( ! $post instanceof \WP_Post || ! function_exists( 'wpml_load_core_tm' ) ) {
			return 0;
		}

		return \WPML\TM\Jobs\TranslationRecord::followPostTranslation(
			$post->ID,
			'post_' . $post->post_type,
			[ 'body' => (string) $post->post_content ]
		);
	}
}
