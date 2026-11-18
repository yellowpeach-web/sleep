<?php

namespace WPML\Compatibility\WPBakery\Hooks;

use WPML\Compatibility\WPBakery\ImageLinks;

class ImageLinksEncoding implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	public function add_hooks() {
		add_filter( 'wpml_pb_shortcode_decode', [ $this, 'decode' ], 10, 3 );
		add_filter( 'wpml_pb_shortcode_encode', [ $this, 'encode' ], 10, 3 );
	}

	public function decode( $content, $encoding, $encodedContent ) {
		return ImageLinks::ENCODING === $encoding ? ImageLinks::decode( $encodedContent ) : $content;
	}

	public function encode( $content, $encoding, $decodedContent ) {
		return ImageLinks::ENCODING === $encoding ? ImageLinks::encode( (array) $decodedContent ) : $content;
	}
}
