<?php

class WPML_Cornerstone_Accordion extends WPML_Cornerstone_Module_With_Items {

	public function get_fields() {
		return array( 'accordion_item_header_content', 'accordion_item_content' );
	}

	protected function get_title( $field ) {
		if ( 'accordion_item_header_content' === $field ) {
			/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
			return esc_html__( 'Accordion: Header', 'sitepress' );
		}

		if ( 'accordion_item_content' === $field ) {
			/* translators: Field label in WPML's translation editor for a page built with Cornerstone and Elementor. Before the colon is the name Cornerstone and Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
			return esc_html__( 'Accordion: Content', 'sitepress' );
		}

		return '';
	}

	protected function get_editor_type( $field ) {
		if ( 'accordion_item_header_content' === $field ) {
			return 'LINE';
		} else {
			return 'VISUAL';
		}
	}
}