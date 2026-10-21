<?php

class WPML_Elementor_Toggle extends WPML_Elementor_Module_With_Items  {

	public function get_items_field() {
		return 'tabs';
	}

	public function get_fields() {
		return array( 'tab_title', 'tab_content' );
	}

	protected function get_title( $field ) {
		switch( $field ) {
			case 'tab_title':
				/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
				return esc_html__( 'Toggle: Title', 'sitepress' );

			case 'tab_content':
				/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
				return esc_html__( 'Toggle: Content', 'sitepress' );

			default:
				return '';
		}
	}

	protected function get_editor_type( $field ) {
		switch( $field ) {
			case 'tab_title':
				return 'LINE';

			case 'tab_content':
				return 'VISUAL';

			default:
				return '';
		}
	}

}
