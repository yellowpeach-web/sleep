<?php
$wpml_ls_native  = isset( $language['native_name'] ) ? $language['native_name'] : null;
$wpml_ls_display = isset( $language['display_name'] ) ? $language['display_name'] : null;

if ( ! empty( $wpml_ls_names_current ) && ! empty( $language['is_current'] ) && ( $wpml_ls_native || $wpml_ls_display ) ) {
	echo '<span class="', wpml_ls_esc( $css_classes_native ), '">', wpml_ls_esc( wpml_ls_default( $wpml_ls_native, $wpml_ls_display ) ), '</span>';
} else {
	if ( $wpml_ls_native ) {
		echo '<span class="', wpml_ls_esc( $css_classes_native ), '"';
		if ( isset( $wpml_ls_names_lang ) ) {
			echo ' lang="', wpml_ls_esc( $wpml_ls_names_lang ), '"';
		}
		echo '>', wpml_ls_esc( $wpml_ls_native ), '</span>';
	}

	if ( $wpml_ls_display && ( $wpml_ls_display != $wpml_ls_native ) ) {
		echo '<span class="', wpml_ls_esc( $css_classes_display ), '">';
		if ( $wpml_ls_native ) {
			echo '<span class="', wpml_ls_esc( $css_classes_bracket ), '"> (</span>';
		}
		echo wpml_ls_esc( $wpml_ls_display );
		if ( $wpml_ls_native ) {
			echo '<span class="', wpml_ls_esc( $css_classes_bracket ), '">)</span>';
		}
		echo '</span>';
	}
}
