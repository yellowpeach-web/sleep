<?php

namespace WPML\PB\Elementor\Hooks;

class HeaderFooterFields implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const NAV_MENU = 'nav-menu';

	const PRIORITY_AFTER_SERVED_CONFIG = 30;

	public function add_hooks() {
		add_filter( 'wpml_elementor_widgets_to_translate', [ $this, 'addMissingFields' ], self::PRIORITY_AFTER_SERVED_CONFIG );
	}

	public function addMissingFields( $widgets ) {
		if ( ! is_array( $widgets ) || ! isset( $widgets[ self::NAV_MENU ] ) || ! is_array( $widgets[ self::NAV_MENU ] ) ) {
			return $widgets;
		}

		$navMenu = $widgets[ self::NAV_MENU ];
		$fields  = isset( $navMenu['fields'] ) && is_array( $navMenu['fields'] ) ? $navMenu['fields'] : [];

		foreach ( $fields as $field ) {
			if ( isset( $field['field'] ) && 'menu_name' === $field['field'] ) {
				return $widgets;
			}
		}

		$fields[] = [
			'field'       => 'menu_name',
			/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
			'type'        => __( 'Menu: Menu name', 'sitepress' ),
			'editor_type' => 'LINE',
		];

		$navMenu['fields']         = $fields;
		$widgets[ self::NAV_MENU ] = $navMenu;

		return $widgets;
	}
}
