<?php

class WPML_Redirect_By_Subdir extends WPML_Redirection {

	public function get_redirect_target() {
		$target = $this->redirect_hidden_home();

		if ( false !== $target ) {
			return $target;
		}

		return $this->removed_language_target( \WPML\Languages\RemovedLanguageRedirect::MODE_DIRECTORY );
	}
}
