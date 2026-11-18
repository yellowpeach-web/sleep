<?php
if ( ! empty( $language['flag_url'] ) ) {
	$wpml_ls_flag_width  = isset( $language['flag_width'] ) ? $language['flag_width'] : null;
	$wpml_ls_flag_height = isset( $language['flag_height'] ) ? $language['flag_height'] : null;

	if ( empty( $wpml_ls_flag_compact ) ) {
		echo wpml_ls_esc( str_repeat( ' ', 12 + ( $wpml_ls_flag_width > 0 ? 12 : 0 ) + ( $wpml_ls_flag_height > 0 ? 12 : 0 ) ) );
	}

	echo "<img\n            class=\"", wpml_ls_esc( isset( $css_classes_flag ) ? $css_classes_flag : '' ), "\"\n";
	echo '            src="', wpml_ls_esc( $language['flag_url'] ), "\"\n";
	echo '            alt="', wpml_ls_esc( isset( $language['flag_alt'] ) ? $language['flag_alt'] : '' ), "\"\n";
	echo "            loading=\"lazy\"\n            ";

	if ( $wpml_ls_flag_width > 0 ) {
		echo wpml_ls_esc( sprintf( 'width=%s', $wpml_ls_flag_width ) );
	} elseif ( isset( $flag_width ) ) {
		echo wpml_ls_esc( $flag_width );
	}

	echo "\n            ";

	if ( $wpml_ls_flag_height > 0 ) {
		echo wpml_ls_esc( sprintf( 'height=%s', $wpml_ls_flag_height ) );
	} elseif ( isset( $flag_height ) ) {
		echo wpml_ls_esc( $flag_height );
	}

	echo "\n    />";
}
