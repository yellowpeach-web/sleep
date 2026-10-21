<?php

class WPML_Beaver_Builder_Accordion extends WPML_Beaver_Builder_Module_With_Items {

	protected function get_title( $field ) {
		switch( $field ) {
			case 'label':
				/* translators: Field label in WPML's translation editor for a page built with Beaver Builder. Before the colon is the name Beaver Builder gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
				return esc_html__( 'Accordion Item: Label', 'sitepress' );

			case 'content':
				/* translators: Field label in WPML's translation editor for a page built with Beaver Builder. Before the colon is the name Beaver Builder gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
				return esc_html__( 'Accordion Item: Content', 'sitepress' );

			default:
				return '';
		}
	}

}
