<?php

namespace WPML\TranslationPolicy\Dashboard;

class Loader implements \IWPML_Backend_Action {

	public function add_hooks() {
		( new MethodPreselect() )->add_hooks();
		( new ClassicEditorHistory() )->add_hooks();
		( new ReviewOpenInAte() )->add_hooks();
	}
}
