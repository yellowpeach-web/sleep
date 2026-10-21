<?php

class WPML_Elementor_Icon_List extends WPML_Elementor_Module_With_Items {

	public function get_items_field() {
		return 'icon_list';
	}

	public function get_fields() {
		return array( 'text', 'link' => array( 'url' ) );
	}

	protected function get_title( $field ) {
		switch( $field ) {
			case 'text':
				/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
				return esc_html__( 'Icon List: Text', 'sitepress' );

			case 'url':
				/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
				return esc_html__( 'Icon List: Link URL', 'sitepress' );

			default:
				return '';
		}
	}

	protected function get_editor_type( $field ) {
		switch( $field ) {
			case 'text':
				return 'LINE';
			case 'url':
				return 'LINK';

			default:
				return '';
		}
	}

}
