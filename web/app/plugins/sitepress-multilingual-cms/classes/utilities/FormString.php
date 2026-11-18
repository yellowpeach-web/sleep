<?php

namespace WPML\Utilities;

class FormString {

	public static function parse( $form ) {
		$fields = [];

		if ( is_string( $form ) && '' !== $form ) {
			parse_str( self::undo_html_escaping( $form ), $fields );
		}

		return $fields;
	}

	public static function undo_html_escaping( $form ) {
		return (string) preg_replace(
			[ '/&(?:amp|#0*38|#x0*26);/i', '/&(?:apos|#0*39|#x0*27);/i' ],
			[ '&', "'" ],
			$form
		);
	}
}
