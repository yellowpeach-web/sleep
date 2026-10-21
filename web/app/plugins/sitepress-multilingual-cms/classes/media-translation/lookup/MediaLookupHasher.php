<?php

namespace WPML\Media\Lookup;

class MediaLookupHasher {

	const SEPARATOR = '#';

	public function hash( $language, $value ) {
		return md5( $language . self::SEPARATOR . $value );
	}

	public function sqlHashExpression( $language_expr, $value_expr ) {
		return "UNHEX(MD5(CONCAT({$language_expr}, '" . self::SEPARATOR . "', {$value_expr})))";
	}

	public function isValidHex( $hex ) {
		return is_string( $hex ) && 32 === strlen( $hex ) && ctype_xdigit( $hex );
	}
}
