<?php

namespace WPML\PB\Elementor\LanguageSwitcher;

use Elementor\Plugin;

class LanguageSwitcher implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	public function add_hooks() {
		if ( version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
			add_action( 'elementor/widgets/register', [ $this, 'registerWidgets' ] );
		} else {
			add_action( 'elementor/widgets/widgets_registered', [ $this, 'registerWidgetsDeprecated' ] );
		}

		add_filter( 'wpml_custom_language_switcher_is_enabled', '__return_true' );
		add_filter( 'wpml_ls_hide_when_nothing_to_switch', [ $this, 'showInElementorEditor' ] );
	}

	public function showInElementorEditor( $hide ) {
		if ( ! class_exists( Plugin::class ) ) {
			return $hide;
		}

		$elementor = Plugin::instance();

		if ( isset( $elementor->editor ) && $elementor->editor->is_edit_mode() ) {
			return false;
		}

		if ( isset( $elementor->preview ) && $elementor->preview->is_preview_mode() ) {
			return false;
		}

		return $hide;
	}

	public function registerWidgets() {
		Plugin::instance()->widgets_manager->register( new Widget() );
	}

	public function registerWidgetsDeprecated() {
		Plugin::instance()->widgets_manager->register_widget_type( new Widget() );
	}
}
