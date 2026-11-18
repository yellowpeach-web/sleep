<?php

namespace WPML\CustomFieldTranslation;

class CopyOnceRecord {

	const META_KEY = '_wpml_copy_once_done';

	public static function copiedFrom( array $meta ) {
		if ( empty( $meta[ self::META_KEY ] ) ) {
			return array();
		}
		return self::decode( is_array( $meta[ self::META_KEY ] ) ? reset( $meta[ self::META_KEY ] ) : $meta[ self::META_KEY ] );
	}

	public static function copied( $postId ) {
		$meta = get_post_meta( (int) $postId );

		return self::copiedFrom( is_array( $meta ) ? $meta : array() );
	}

	public static function record( $postId, $metaKey ) {
		$keys   = self::copied( $postId );
		$keys[] = (string) $metaKey;
		update_post_meta( (int) $postId, self::META_KEY, self::encode( $keys ) );
	}

	public static function forget( $postId, array $metaKeys ) {
		$keys = self::copied( $postId );
		if ( ! $keys ) {
			return;
		}
		$left = array_values( array_diff( $keys, array_map( 'strval', $metaKeys ) ) );
		if ( $left === $keys ) {
			return;
		}
		if ( $left ) {
			update_post_meta( (int) $postId, self::META_KEY, self::encode( $left ) );
		} else {
			delete_post_meta( (int) $postId, self::META_KEY );
		}
	}

	private static function decode( $raw ) {
		$keys = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : array();

		return array_values( array_filter( is_array( $keys ) ? $keys : array(), 'is_string' ) );
	}

	private static function encode( array $keys ) {
		return (string) json_encode( array_values( array_unique( $keys ) ) );
	}
}
