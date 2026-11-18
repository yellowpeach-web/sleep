<?php

namespace WPML\PB\Cornerstone\Hooks;

class AssignmentsFactory implements \IWPML_Frontend_Action_Loader, \IWPML_Backend_Action_Loader {

	public function create() {
		global $wpml_post_translations;

		return new Assignments( $wpml_post_translations );
	}
}
