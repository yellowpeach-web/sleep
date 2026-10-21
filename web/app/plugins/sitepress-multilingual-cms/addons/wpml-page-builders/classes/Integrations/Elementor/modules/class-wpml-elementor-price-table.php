<?php

class WPML_Elementor_Price_Table extends WPML_Elementor_Module_With_Items {

	public function get_items_field() {
		return 'features_list';
	}

	public function get_fields() {
		return array( 'item_text' );
	}

	protected function get_title( $field ) {
		if ( 'item_text' === $field ) {
			/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
			return esc_html__( 'Price Table: text', 'sitepress' );
		}

		return '';
	}

	protected function get_editor_type( $field ) {
		if ( 'item_text' === $field ) {
			return 'LINE';
		}

		return '';
	}
}
