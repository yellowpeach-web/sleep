<?php

namespace WPML\EditHistory;

class PostCopy {

	const FIELDS = [ 'post_title', 'post_name', 'post_excerpt', 'post_content' ];

	private $metaKeys;

	public function __construct( callable $metaKeys ) {
		$this->metaKeys = $metaKeys;
	}

	public function read( int $postId, int $originalId ) {
		$post     = get_post( $postId );
		$original = get_post( $originalId );
		if ( ! $post instanceof \WP_Post || ! $original instanceof \WP_Post ) {
			return null;
		}

		$meta = [];
		foreach ( ( $this->metaKeys )( $original ) as $key ) {
			$meta[ $key ] = get_post_meta( $postId, $key, false );
		}

		return [
			'post' => array_intersect_key( get_object_vars( $post ), array_flip( self::FIELDS ) ),
			'meta' => $meta,
		];
	}

	public static function write( int $postId, array $copy ): bool {
		foreach ( (array) ( $copy['meta'] ?? [] ) as $key => $values ) {
			delete_post_meta( $postId, (string) $key );
			foreach ( (array) $values as $value ) {
				add_post_meta( $postId, (string) $key, wp_slash( $value ) );
			}
		}

		$fields = array_intersect_key( (array) ( $copy['post'] ?? [] ), array_flip( self::FIELDS ) );

		kses_remove_filters();
		try {
			$saved = wp_update_post( wp_slash( [ 'ID' => $postId ] + $fields ), true );
		} finally {
			kses_init();
		}

		return is_int( $saved ) && $saved > 0;
	}

	public static function units( PostUnits $units, \WP_Post $post, \WP_Post $original, array $copy ): array {
		$fields = array_intersect_key( (array) ( $copy['post'] ?? [] ), array_flip( self::FIELDS ) );
		$meta   = (array) ( $copy['meta'] ?? [] );
		$saved  = new \WP_Post( (object) array_merge( get_object_vars( $post ), $fields ) );
		$answer = static function ( $value, $objectId, $key ) use ( $post, $meta ) {
			return (int) $objectId === (int) $post->ID && array_key_exists( (string) $key, $meta ) ? ( (array) $meta[ $key ] ?: [ '' ] ) : $value;
		};

		add_filter( 'get_post_metadata', $answer, 10, 3 );
		try {
			return $units->read( $saved, $original );
		} finally {
			remove_filter( 'get_post_metadata', $answer, 10 );
		}
	}
}
