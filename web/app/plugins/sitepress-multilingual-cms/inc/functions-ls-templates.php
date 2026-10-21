<?php


if ( ! function_exists( 'wpml_ls_flag_html' ) ) {
	function wpml_ls_flag_html( $language, $css_class = 'wpml-ls-flag' ) {
		if ( ! is_array( $language ) || empty( $language['flag_url'] ) ) {
			return '';
		}

		$alt    = isset( $language['flag_alt'] ) ? $language['flag_alt'] : '';
		$width  = isset( $language['flag_width'] ) && $language['flag_width'] > 0 ? ' width="' . (int) $language['flag_width'] . '"' : '';
		$height = isset( $language['flag_height'] ) && $language['flag_height'] > 0 ? ' height="' . (int) $language['flag_height'] . '"' : '';

		return '<img class="' . esc_attr( $css_class ) . '" src="' . esc_url( $language['flag_url'] ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy"' . $width . $height . ' />';
	}
}
