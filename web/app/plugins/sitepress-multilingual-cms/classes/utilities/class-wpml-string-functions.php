<?php

class WPML_String_Functions {

	const SHORTCODE_PATTERN = '/\[\/?[\w-]+(?:\s[^\[\]]*)?\/?\]/u';

	const TRANSLATABLE_ATTRIBUTE_PATTERN = '/\b(?:alt|title|placeholder|aria-label|value)\s*=\s*(["\'])\s*(?!\1)\S/i';

	const RESOURCE_ATTRIBUTE_PATTERN = '/(?<![\w-])(?:src|href|poster|srcset)\s*=\s*(["\'])\s*(?!\1)\S/i';

	public static function is_css_color( $string ) {
		return (bool) preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/im', $string );
	}

	public static function is_css_length( $string ) {
		$parts = explode( ' ', $string );
		foreach ( $parts as $part ) {
			if ( ! (bool) preg_match( '/^[+-]?[0-9]+.?([0-9]+)?(px|em|ex|ch|rem|vw|vh|vmin|vmax|%|in|cm|mm|pt|pc)$/im', $part ) &&
			   '0' !== $part ) {
				return false;
			}
		}

		return true;
	}

	public static function is_numeric( $string ) {
		return (bool) is_numeric( $string );
	}

	public static function is_not_translatable( $string ) {
		return self::is_css_color( $string ) ||
			   self::is_css_length( $string ) ||
			   self::is_numeric( $string ) ||
			   self::is_textless( $string );
	}

	public static function has_no_text( $source ) {
		$text = html_entity_decode( strip_tags( (string) $source ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( self::SHORTCODE_PATTERN, '', (string) $text );

		return '' === preg_replace( '/(*UCP)\s+/u', '', (string) $text );
	}

	public static function is_textless( $string ) {
		return is_string( $string )
			&& '' !== trim( $string )
			&& self::has_no_text( $string )
			&& ! preg_match( self::TRANSLATABLE_ATTRIBUTE_PATTERN, $string )
			&& ! preg_match( self::RESOURCE_ATTRIBUTE_PATTERN, $string );
	}
}
