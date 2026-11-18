<?php

use WPML\API\Sanitize;

class WPML_Troubleshooting_Terms_Menu {

	public static function display_terms_with_suffix() {

		$terms_to_display = WPML_Terms_Translations::get_all_terms_with_language_suffix();

		$output = '';

		if ( ! empty( $terms_to_display ) ) {

			$output  = '<div class="icl_cyan_box">';
			$output .= '<table class="widefat" id="icl-updated-term-names-table">';
			$output .= '<a name="termsuffixupdate"></a>';
			$output .= '<tr><h3>' . __( 'Remove language suffixes from taxonomy names.', 'sitepress' ) . '</h3></tr>';

			$output .= '<tr id="icl-updated-term-names-headings"><th></th><th>' . /* translators: Column heading in the table of renamed terms on the Troubleshooting screen: the name the term had before. Noun phrase. */ __( 'Old Name', 'sitepress' ) . '</th><th>' . /* translators: Column heading in the table of renamed terms on the Troubleshooting screen: the name the term has now. Noun phrase. */ __( 'Updated Name', 'sitepress' ) . '</th><th>' . /* translators: Column heading in the table of renamed terms on the Troubleshooting screen: the kinds of grouping the change reaches. */ __( 'Affected Taxonomies', 'sitepress' ) . '</th></tr>';

			foreach ( $terms_to_display as $term_id => $term ) {

				$updated_term_name = self::strip_language_suffix( $term['name'] );

				$output .= '<tr class="icl-term-with-suffix-row"><td>';
				$output .= '<input type="checkbox" checked="checked" name="' . esc_attr( $updated_term_name ) . '" value="' . (int) $term_id . '"/>';
				$output .= '</td>';
				$output .= '<td>' . esc_html( $term['name'] ) . '</td>';
				$output .= '<td id="term_' . (int) $term_id . '">' . esc_html( $updated_term_name ) . '</td>';
				$output .= '<td>' . esc_html( join( ', ', $term['taxonomies'] ) ) . '</td>';
				$output .= '</tr>';
			}
			$output .= '</table>';

			$output .= '</br></br>';
			$output .= '<button id="icl-update-term-names" class="button-primary">' . __( 'Update term names', 'sitepress' ) . '</button>';
			$output .= '<button id="icl-update-term-names-done" class="button-primary" disabled="disabled" style="display:none;">' . __( 'All term names updated', 'sitepress' ) . '</button>';
			$output .= '</div>';
		}

		return $output;
	}

	public static function strip_language_suffix( $term_name ) {
		global $wpdb;

		$lang_codes = $wpdb->get_col( "SELECT code FROM {$wpdb->prefix}icl_languages" );

		$new_name_parts = explode( ' @', $term_name );

		$new_name_parts = array_filter( $new_name_parts );

		$last_part = array_pop( $new_name_parts );

		while ( in_array( $last_part, $lang_codes ) ) {
			$last_part = array_pop( $new_name_parts );
		}

		$new_name = '';
		if ( ! empty( $new_name_parts ) ) {
			$new_name = join( ' @', $new_name_parts ) . ' @';
		}

		$new_name .= $last_part;

		return $new_name;
	}

	public static function wpml_update_term_names_troubleshoot() {
		global $wpdb;

		$nonce = Sanitize::stringProp( '_icl_nonce', $_POST );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'update_term_names_nonce' ) ) {
			die( 'Wrong Nonce' );
		}

		$raw_terms  = isset( $_POST['terms'] ) ? wp_unslash( $_POST['terms'] ) : '';
		$term_names = is_string( $raw_terms ) && '' !== $raw_terms ? json_decode( $raw_terms, true ) : array();
		if ( ! is_array( $term_names ) ) {
			$term_names = array();
		}

		$updated = array();

		foreach ( $term_names as $term_id => $new_name ) {
			$term_id = (int) $term_id;
			if ( $term_id <= 0 || ! is_string( $new_name ) ) {
				continue;
			}
			$new_name = sanitize_text_field( $new_name );
			if ( '' === $new_name ) {
				continue;
			}
			$res = $wpdb->update( $wpdb->terms, array( 'name' => $new_name ), array( 'term_id' => $term_id ) );
			if ( false !== $res ) {
				$updated[] = $term_id;
			}
		}

		wp_send_json_success( $updated );
	}
}
