<?php

class WPML_Beaver_Builder_Content_Slider extends WPML_Beaver_Builder_Module_With_Items {

	public function &get_items( $settings ) {
		return $settings->slides;
	}

	public function get_fields() {
		return array( 'title', 'text', 'cta_text', 'link' );
	}

	protected function get_title( $field ) {
		switch( $field ) {
			case 'title':
				/* translators: Field label in WPML's translation editor for a page built with Beaver Builder. Before the colon is the name Beaver Builder gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
				return esc_html__( 'Content Slider: Slide heading', 'sitepress' );

			case 'text':
				/* translators: Field label in WPML's translation editor for a page built with Beaver Builder. Before the colon is the name Beaver Builder gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
				return esc_html__( 'Content Slider: Slide content', 'sitepress' );

			case 'cta_text':
				/* translators: Field label in WPML's translation editor for a page built with Beaver Builder. Before the colon is the name Beaver Builder gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
				return esc_html__( 'Content Slider: Slide call to action text', 'sitepress' );

			case 'link':
				/* translators: Field label in WPML's translation editor for a page built with Beaver Builder. Before the colon is the name Beaver Builder gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
				return esc_html__( 'Content Slider: Slide call to action link', 'sitepress' );

			default:
				return '';
		}
	}

	protected function get_editor_type( $field ) {
		switch( $field ) {
			case 'title':
			case 'cta_text':
				return 'LINE';

			case 'link':
				return 'LINK';

			case 'text':
				return 'VISUAL';

			default:
				return '';
		}
	}

}
