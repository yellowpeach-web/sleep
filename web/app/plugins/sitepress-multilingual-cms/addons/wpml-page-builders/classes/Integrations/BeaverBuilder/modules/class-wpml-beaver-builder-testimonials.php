<?php

class WPML_Beaver_Builder_Testimonials extends WPML_Beaver_Builder_Module_With_Items {

	public function &get_items( $settings ) {
		return $settings->testimonials;
	}

	public function get_fields() {
		return array( 'testimonial' );
	}

	protected function get_title( $field ) {
		switch( $field ) {
			case 'testimonial':
				/* translators: Field label in WPML's translation editor for a page built with Beaver Builder. Before the colon is the name Beaver Builder gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
				return esc_html__( 'Testimonial: content', 'sitepress' );

			default:
				return '';
		}
	}

	protected function get_editor_type( $field ) {
		switch( $field ) {
			case 'testimonial':
				return 'VISUAL';

			default:
				return '';
		}
	}

}
