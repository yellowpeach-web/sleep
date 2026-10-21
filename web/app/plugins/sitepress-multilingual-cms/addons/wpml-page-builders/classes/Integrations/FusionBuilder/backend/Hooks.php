<?php

namespace WPML\Compatibility\FusionBuilder\Backend;

class Hooks implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	public function add_hooks() {
		add_action( 'wp_update_nav_menu_item', [ $this, 'invalidateMegamenuHook' ], 1 );
	}

	public function invalidateMegamenuHook() {
		if ( ! apply_filters( 'wpml_is_operation', false, 'sync_menus' ) ) {
			return;
		}

		global $mega_menu_framework;
		if ( ! isset( $mega_menu_framework ) || empty( $mega_menu_framework::$classes['menus'] ) ) {
			return;
		}

		remove_action( 'wp_update_nav_menu_item', [ $mega_menu_framework::$classes['menus'], 'save_custom_menu_style_fields' ], 10 );
	}
}
