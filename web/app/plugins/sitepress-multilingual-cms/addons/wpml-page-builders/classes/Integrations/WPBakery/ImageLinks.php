<?php

namespace WPML\Compatibility\WPBakery;

class ImageLinks {

	const ENCODING = 'wpb9_image_links';

	private const URL_KEY = 'url';

	private const TRANSLATABLE_KEYS = [ self::URL_KEY, 'title' ];

	private const ATTRIBUTES = [
		'vc_single_image' => 'image',
		'vc_gallery'      => 'images',
	];

	public static function addStringSettings( array $stringSettings ) {
		foreach ( self::ATTRIBUTES as $tag => $attribute ) {
			foreach ( $stringSettings as $setting ) {
				if (
					$tag === $setting['tag']['value']
					&& in_array( $attribute, array_column( (array) $setting['attributes'], 'value' ), true )
				) {
					continue 2;
				}
			}

			$stringSettings[] = [
				'tag'        => [
					'value'          => $tag,
					'encoding'       => '',
					'ignore-content' => false,
				],
				'attributes' => [
					[
						'value'    => $attribute,
						'encoding' => self::ENCODING,
						'type'     => '',
						'label'    => '',
					],
				],
			];
		}

		return $stringSettings;
	}

	public static function isImageAttribute( $tag, $attribute ) {
		return isset( self::ATTRIBUTES[ $tag ] ) && self::ATTRIBUTES[ $tag ] === $attribute;
	}

	public static function decodeMap( $value ) {
		if ( ! is_string( $value ) || '{' !== substr( ltrim( $value ), 0, 1 ) ) {
			return null;
		}

		$map = json_decode( str_replace( [ '`{`', '`}`', '``' ], [ '[', ']', '"' ], $value ), true );

		if ( ! is_array( $map ) ) {
			return null;
		}

		foreach ( $map as $id => $link ) {
			if ( ! is_int( $id ) || ! is_array( $link ) || array_filter( $link, 'is_array' ) ) {
				return null;
			}
		}

		return $map;
	}

	private static function encodeMap( array $map ) {
		$json = (string) wp_json_encode( (object) $map, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return str_replace( [ '"', '[', ']' ], [ '``', '`{`', '`}`' ], $json );
	}

	public static function decode( $value ) {
		$strings = [];

		foreach ( (array) self::decodeMap( $value ) as $id => $link ) {
			foreach ( $link as $key => $linkValue ) {
				$strings[ $key . '_' . $id ] = [
					'value'     => (string) $linkValue,
					'translate' => in_array( $key, self::TRANSLATABLE_KEYS, true ),
				];

				if ( self::URL_KEY === $key ) {
					$strings[ $key . '_' . $id ]['type'] = \WPML_TM_Page_Builders::FIELD_STYLE_LINK;
				}
			}
		}

		return $strings;
	}

	public static function encode( array $strings ) {
		$map = [];

		foreach ( $strings as $name => $value ) {
			$parts = explode( '_', $name );
			$id    = array_pop( $parts );

			$map[ $id ][ implode( '_', $parts ) ] = $value;
		}

		return self::encodeMap( $map );
	}

	public static function translateIds( $value, callable $translateId ) {
		$map = self::decodeMap( $value );

		if ( null === $map ) {
			return null;
		}

		$translated = [];

		foreach ( $map as $id => $link ) {
			$translatedId = (string) $translateId( $id );

			if ( ! isset( $translated[ $translatedId ] ) ) {
				$translated[ $translatedId ] = $link;
			}
		}

		return self::encodeMap( $translated );
	}
}
