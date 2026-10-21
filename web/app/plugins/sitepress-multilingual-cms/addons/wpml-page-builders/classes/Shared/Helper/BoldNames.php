<?php

namespace WPML\PB\Helper;

class BoldNames {

	public static function render( $text, array $allowed = [] ) {
		if ( function_exists( 'wpml_bold_names' ) ) {
			return wpml_bold_names( $text, $allowed );
		}

		return wp_kses(
			(string) $text,
			array_merge( [ 'b' => [ 'class' => [] ] ], $allowed )
		);
	}
}
