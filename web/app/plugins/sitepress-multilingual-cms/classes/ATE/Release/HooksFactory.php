<?php

namespace WPML\TM\ATE\Release;

class HooksFactory implements \IWPML_Backend_Action_Loader, \IWPML_REST_Action_Loader, \IWPML_AJAX_Action_Loader {

	public function create() {
		return \WPML_TM_ATE_Status::is_enabled_and_activated() ? new Hooks() : null;
	}
}
