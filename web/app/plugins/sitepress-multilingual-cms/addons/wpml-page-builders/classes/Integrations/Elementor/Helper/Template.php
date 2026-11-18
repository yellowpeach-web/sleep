<?php

namespace WPML\PB\Elementor\Helper;

class Template {

	const POST_TYPE = \WPML\PB\Elementor\Hooks\Templates::POST_TYPE;

	public static function fallBackWhenUnpublished( $originalId, $translatedId, $postType ) {
		if ( self::POST_TYPE !== $postType || 'publish' === get_post_status( (int) $translatedId ) ) {
			return $translatedId;
		}

		if ( (int) $translatedId !== (int) $originalId ) {
			return $originalId;
		}

		$sourceId = (int) apply_filters( 'wpml_original_element_id', null, (int) $originalId, 'post_' . self::POST_TYPE );

		if ( ! $sourceId || $sourceId === (int) $originalId ) {
			return $originalId;
		}

		return is_string( $originalId ) ? (string) $sourceId : $sourceId;
	}
}
