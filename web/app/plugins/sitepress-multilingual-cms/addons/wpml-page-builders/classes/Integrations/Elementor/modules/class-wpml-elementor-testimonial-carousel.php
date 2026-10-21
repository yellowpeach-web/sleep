<?php

class WPML_Elementor_Testimonial_Carousel extends WPML_Elementor_Module_With_Items  {

	public function get_items_field() {
		return 'slides';
	}

	public function get_fields() {
		return array( 'content', 'name', 'title' );
	}

	protected function get_title( $field ) {
		switch( $field ) {
			case 'content':
				/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
				return esc_html__( 'Testimonial Carousel: Content', 'sitepress' );

			case 'name':
				/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
				return esc_html__( 'Testimonial Carousel: Name', 'sitepress' );

			case 'title':
				/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
				return esc_html__( 'Testimonial Carousel: Title', 'sitepress' );

			default:
				return '';
		}
	}

	protected function get_editor_type( $field ) {
		switch( $field ) {
			case 'name':
				return 'LINE';

			case 'title':
				return 'LINE';

			case 'content':
				return 'VISUAL';

			default:
				return '';
		}
	}

}
