<?php

class WPML_Cornerstone_Tabs extends WPML_Cornerstone_Module_With_Items {

	public function get_fields() {
		return array( 'tab_label_content', 'tab_content' );
	}

	protected function get_title( $field ) {
		if ( 'tab_label_content' === $field ) {
			/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
			return esc_html__( 'Tabs: Tab Label', 'sitepress' );
		}

		if ( 'tab_content' === $field ) {
			/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
			return esc_html__( 'Tabs: Tab Content', 'sitepress' );
		}

		return '';
	}

	protected function get_editor_type( $field ) {
		if ( 'tab_label_content' === $field ) {
			return 'LINE';
		} else {
			return 'VISUAL';
		}
	}
}