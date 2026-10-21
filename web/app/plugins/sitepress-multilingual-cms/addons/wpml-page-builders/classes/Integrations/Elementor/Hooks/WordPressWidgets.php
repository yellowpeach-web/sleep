<?php

namespace WPML\PB\Elementor\Hooks;

class WordPressWidgets implements \IWPML_Backend_Action {

	public function add_hooks() {
		\WPML\PB\Request\Ajax::listen(
			'elementor_ajax',
			function() {
				add_filter( 'wpml_widget_language_selector_disable', '__return_true' );
			},
			'Elementor editor AJAX: disables the WPML widget language selector for the host request only; Elementor enforces its own nonce and capabilities'
		);
	}
}
