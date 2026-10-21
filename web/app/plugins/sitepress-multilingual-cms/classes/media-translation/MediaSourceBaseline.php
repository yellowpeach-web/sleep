<?php

namespace WPML\MediaTranslation;

class MediaSourceBaseline {

	const META_PREFIX = '_wpml_media_source_';

	private static $comparable_fields = array( 'title', 'caption', 'description', 'alt_text' );

	public static function isComparable( $media_field ) {
		return in_array( $media_field, self::$comparable_fields, true );
	}

	public static function key( $media_field, $lang ) {
		return self::META_PREFIX . $media_field . '_' . $lang;
	}

	public static function read( $attachment_id, $media_field, $lang ) {
		$stored = get_post_meta( (int) $attachment_id, self::key( $media_field, $lang ), true );

		return '' === $stored || false === $stored || null === $stored ? null : (string) $stored;
	}

	public static function record( $attachment_id, $media_field, $lang, $source ) {
		if ( ! self::isComparable( $media_field ) || ! $attachment_id || ! $lang ) {
			return;
		}

		update_post_meta( (int) $attachment_id, self::key( $media_field, $lang ), (string) $source );
	}
}
