<?php

namespace WPML\TM\AutomaticTranslation\Actions;

use WPML\TM\ATE\Cancel\CancelSource;

class SaveSource {

	const TRANSIENT_PREFIX = 'wpml_tm_save_content_changed_';

	const TTL = 600;

	public static function remember( $postId, $after, $before ) {
		if ( ! $after instanceof \WP_Post || ! $before instanceof \WP_Post || 'revision' === $after->post_type ) {
			return;
		}

		foreach ( [ 'post_title', 'post_content', 'post_excerpt' ] as $field ) {
			if ( (string) $after->{$field} !== (string) $before->{$field} ) {
				set_transient( self::TRANSIENT_PREFIX . (int) $postId, 1, self::TTL );

				return;
			}
		}
	}

	public static function consume( $postId ) {
		$key     = self::TRANSIENT_PREFIX . (int) $postId;
		$changed = (bool) get_transient( $key );

		if ( $changed ) {
			delete_transient( $key );
		}

		return $changed ? CancelSource::SAVE_CONTENT : CancelSource::SAVE_META;
	}
}
