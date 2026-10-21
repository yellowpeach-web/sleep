<?php

class WPML_Redirect_By_Domain extends WPML_Redirection {

	public function get_redirect_target() {
		return $this->redirect_hidden_home();
	}
}
