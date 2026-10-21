<?php

namespace WPML\Languages;

class HreflangTag {

	public static function forLanguage( array $lang ): string {
		$orderedKeys = [ 'tag', 'default_locale' ];

		$tag = '';
		foreach ( $orderedKeys as $key ) {
			if ( array_key_exists( $key, $lang ) && trim( (string) $lang[ $key ] ) ) {
				$tag = (string) $lang[ $key ];
				break;
			}
		}

		$tag = trim( str_replace( '_', '-', $tag ) );

		return strlen( $tag ) >= 2 ? $tag : '';
	}
}
