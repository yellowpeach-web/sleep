<?php

namespace WPML\Compatibility\FusionBuilder;

use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class PlainTextFallback implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const CONTROL_CHARACTERS = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/';

	public function add_hooks() {
		Hooks::onFilter( 'wpml_pb_shortcode_decode', 10, 3 )->then( spreadArgs( [ $this, 'keepPlainText' ] ) );
	}

	public function keepPlainText( $decoded, $encoding, $original ) {
		if (
			\WPML_PB_Shortcode_Encoding::ENCODE_TYPES_BASE64 !== $encoding
			|| ! is_string( $original )
			|| $this->isBase64( $original )
		) {
			return $decoded;
		}

		return $original;
	}

	private function isBase64( $value ) {
		$stripped = wp_strip_all_tags( $value );
		$bytes = base64_decode( $stripped, true );

		return false !== $bytes
			&& base64_encode( $bytes ) === $stripped
			&& 1 === preg_match( '//u', $bytes )
			&& ! preg_match( self::CONTROL_CHARACTERS, $bytes );
	}
}
