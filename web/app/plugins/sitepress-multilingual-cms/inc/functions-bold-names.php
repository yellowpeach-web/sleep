<?php

if ( ! function_exists( 'wpml_bold_names' ) ) {
	function wpml_bold_names( $text, array $allowed = array() ) {
		return wp_kses(
			str_replace( '<b>', '<b class="wpml-name">', (string) $text ),
			array_merge( array( 'b' => array( 'class' => array() ) ), $allowed )
		);
	}
}
