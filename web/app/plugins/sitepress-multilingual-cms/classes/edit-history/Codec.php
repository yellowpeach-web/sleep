<?php

namespace WPML\EditHistory;

use WPML\Translation\TranslationElements\FieldCompression;

class Codec {

	public static function encode( array $value ): string {
		return (string) FieldCompression::compress( (string) wp_json_encode( $value ), false );
	}

	public static function decode( $stored ) {
		if ( null === $stored || '' === $stored ) {
			return null;
		}

		$value = json_decode( (string) FieldCompression::decompress( $stored ), true );

		return is_array( $value ) ? $value : null;
	}
}
