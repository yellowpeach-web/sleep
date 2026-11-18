<?php

namespace WPML\PB\Cornerstone\Hooks;

class BuilderSave implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	public function add_hooks() {
		add_filter( 'cs_wpml_enable_page_builder_integration', '__return_true' );
	}
}
