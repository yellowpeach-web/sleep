<?php
class WPML_Term_Language_Filter extends WPML_Language_Filter_Bar {

	function terms_language_filter( $echo = true ) {
		$this->init();
		$requested_data  = $this->sanitize_request();
		$taxonomy        = $requested_data['req_tax'] !== '' ? $requested_data['req_tax'] : 'post_tag';
		$post_type       = $requested_data['req_ptype'] !== '' ? $requested_data['req_ptype'] : '';
		$languages       = $this->get_counts( $taxonomy );
		$languages_links = array();
		foreach ( $this->active_languages as $code => $lang ) {
			$languages_links[] = $this->lang_element( $languages, $code, $taxonomy, $post_type );
		}
		$all_languages_links = join( ' | ', $languages_links );

		$html = '<span id="icl_subsubsub" class="icl_subsubsub" style="display: none;">' . $all_languages_links . '</span>';
		if ( $echo !== false ) {
			echo $html;
		}

		return $html;
	}

	private function lang_element( $languages, $code, $taxonomy, $post_type ) {
		$count = isset( $languages[ $code ] ) ? $languages[ $code ] : 0;
		if ( $code === $this->current_language ) {
			list($px, $sx) = $this->strong_lang_span_cover( $code, $count );
		} else {
			$px  = '<a href="?taxonomy=' . esc_attr( $taxonomy ) . '&amp;lang=' . esc_attr( $code );
			$px .= $post_type !== '' ? '&amp;post_type=' . esc_attr( $post_type ) : '';
			$px .= '">';
			$sx  = '</a>' . $this->lang_span( $code, $count );
		}

		return $px . esc_html( $this->active_languages[ $code ]['display_name'] ) . $sx;
	}

	protected function get_count_data( $taxonomy ) {
		$wpdb = $this->wpdb;

		if ( empty( $this->active_languages ) ) {
			return array();
		}

		$languages = array_keys( $this->active_languages );

		$language_snippet = apply_filters(
			'wpml_language_filter_extra_conditions_snippet',
			"AND t.language_code IN (" . implode( ', ', array_fill( 0, count( $languages ), '%s' ) ) . ") GROUP BY t.language_code"
		);

		$sql = "SELECT t.language_code, COUNT(tm.term_id) AS c
				 FROM {$wpdb->prefix}icl_translations t
				 JOIN {$wpdb->term_taxonomy} tt
					ON t.element_id = tt.term_taxonomy_id
					AND t.element_type = CONCAT('tax_', tt.taxonomy)
				 JOIN {$wpdb->terms} tm ON tt.term_id = tm.term_id
				 WHERE tt.taxonomy = %s
					{$language_snippet}";

		return $wpdb->get_results(
			$wpdb->prepare( $sql, array_merge( array( $taxonomy ), $languages ) )
		);
	}
}
