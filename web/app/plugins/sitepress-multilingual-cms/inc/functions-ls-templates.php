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

if ( ! function_exists( 'wpml_ls_esc' ) ) {
	function wpml_ls_esc( $value ) {
		return htmlspecialchars( is_scalar( $value ) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'wpml_ls_default' ) ) {
	function wpml_ls_default( $value, $default = '' ) {
		return ( '' === $value || false === $value || null === $value || array() === $value ) ? $default : $value;
	}
}

if ( ! function_exists( 'wpml_ls_core_css_classes' ) ) {
	function wpml_ls_core_css_classes( $backward_compatibility ) {
		$bc = is_array( $backward_compatibility ) ? $backward_compatibility : array();

		return array(
			'flag'    => trim( 'wpml-ls-flag ' . ( isset( $bc['css_classes_flag'] ) ? $bc['css_classes_flag'] : '' ) ),
			'native'  => trim( 'wpml-ls-native ' . ( isset( $bc['css_classes_native'] ) ? $bc['css_classes_native'] : '' ) ),
			'display' => trim( 'wpml-ls-display ' . ( isset( $bc['css_classes_display'] ) ? $bc['css_classes_display'] : '' ) ),
			'bracket' => trim( 'wpml-ls-bracket ' . ( isset( $bc['css_classes_bracket'] ) ? $bc['css_classes_bracket'] : '' ) ),
		);
	}
}
