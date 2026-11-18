<?php

namespace WPML\PB\Elementor\Helper;

class DynamicTag {

	public static function mapSetting( $tag, array $allowedNames, $key, callable $convert ) {
		preg_match( '/name="(.*?(?="))"/', $tag, $tagNameMatch );

		if ( ! $tagNameMatch || ! in_array( $tagNameMatch[1], $allowedNames, true ) ) {
			return $tag;
		}

		return preg_replace_callback( '/settings="(.*?(?="]))/', function( array $matches ) use ( $key, $convert ) {
			$settings = json_decode( urldecode( $matches[1] ), true );

			if ( ! isset( $settings[ $key ] ) ) {
				return $matches[0];
			}

			$settings[ $key ] = $convert( $settings[ $key ] );
			$replace          = urlencode( json_encode( $settings ) );

			return str_replace( $matches[1], $replace, $matches[0] );

		}, $tag );
	}
}
