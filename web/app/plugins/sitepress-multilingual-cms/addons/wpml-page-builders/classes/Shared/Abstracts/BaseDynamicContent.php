<?php

namespace WPML\Compatibility;

use SitePress;

abstract class BaseDynamicContent implements \IWPML_DIC_Action, \IWPML_Backend_Action, \IWPML_Frontend_Action {

	private $sitepress;

	public function __construct( SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		if ( $this->sitepress->is_setup_complete() ) {
			add_filter( 'wpml_pb_shortcode_decode', [ $this, 'guardDecodersAgainstNonStringValues' ], 10, 2 );
			add_filter( 'wpml_pb_shortcode_encode', [ $this, 'encode_dynamic_content' ], 10, 2 );
		}
	}

	public function guardDecodersAgainstNonStringValues( $content, $encoding ) {
		return is_string( $content ) ? $this->decode_dynamic_content( $content, $encoding ) : $content;
	}

	abstract public function decode_dynamic_content( $string, $encoding );

	abstract public function encode_dynamic_content( $string, $encoding );

	abstract protected function is_dynamic_content( $string );

	abstract protected function decode_field( $string );

	abstract protected function encode_field( $field );
}
