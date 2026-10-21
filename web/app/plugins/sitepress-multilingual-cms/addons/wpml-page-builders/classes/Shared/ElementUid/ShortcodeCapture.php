<?php

namespace WPML\PB\ElementUid;

class ShortcodeCapture {

	const SOURCE = 'shortcode';

	private $registry;

	private $nameMap;

	private $maps = [];

	private $hashes = [];

	public function __construct( Registry $registry, NameMap $nameMap ) {
		$this->registry = $registry;
		$this->nameMap  = $nameMap;
	}

	public function onString( $postId, $content, $shortcode ) {
		if ( ! is_string( $content ) || '' === $content || ! is_array( $shortcode ) || (int) $postId <= 0 ) {
			return;
		}

		$uid = self::getUidFromShortcode( $shortcode );

		if ( null === $uid ) {
			return;
		}

		$postId = (int) $postId;

		if ( ! isset( $this->maps[ $postId ] ) ) {
			$this->maps[ $postId ] = [];
		}

		NameMap::add( $this->maps[ $postId ], md5( $content ), $uid );

		if ( ! isset( $this->hashes[ $postId ][ $uid ] ) ) {
			$this->hashes[ $postId ][ $uid ] = self::hash( $shortcode );
		}
	}

	public static function hash( array $shortcode ) {
		$tag        = isset( $shortcode['tag'] ) ? $shortcode['tag'] : '';
		$attributes = isset( $shortcode['attributes'] ) ? $shortcode['attributes'] : '';
		$inner      = isset( $shortcode['content'] ) ? $shortcode['content'] : '';

		return Hash::of( [ $tag, $attributes, $inner ] );
	}

	public static function hasUidAttribute( $content ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return false;
		}

		$names = array_map( 'preg_quote', self::getUidAttributes() );

		return (bool) preg_match( '/\s(?:' . implode( '|', $names ) . ')=/', $content );
	}

	public function flush( $postId = null ) {
		if ( null !== $postId ) {
			$postId = (int) $postId;

			if ( $postId <= 0 ) {
				return;
			}

			if ( ! isset( $this->maps[ $postId ] ) ) {
				$this->nameMap->save( $postId, [], self::SOURCE );
				$this->registry->update( $postId, [], self::SOURCE );

				return;
			}
		}

		$toFlush = null === $postId ? $this->maps : [ $postId => $this->maps[ $postId ] ];

		foreach ( $toFlush as $mapPostId => $map ) {
			$this->nameMap->save( $mapPostId, $map, self::SOURCE );
			$this->registry->update(
				$mapPostId,
				isset( $this->hashes[ $mapPostId ] ) ? $this->hashes[ $mapPostId ] : [],
				self::SOURCE
			);
		}
	}

	public static function getUidFromShortcode( array $shortcode ) {
		$raw = isset( $shortcode['attributes'] ) ? $shortcode['attributes'] : '';

		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return null;
		}

		$attributes = (array) shortcode_parse_atts( $raw );

		foreach ( self::getUidAttributes() as $attributeName ) {
			if ( ! empty( $attributes[ $attributeName ] ) && is_string( $attributes[ $attributeName ] ) ) {
				return $attributes[ $attributeName ];
			}
		}

		return null;
	}

	private static function getUidAttributes() {
		return apply_filters( 'wpml_pb_shortcode_uid_attributes', [ 'av_uid' ] );
	}
}
