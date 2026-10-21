<?php

namespace WPML\TM\ATE\TranslateEverything;

class PendingOfferHooksFactory implements \IWPML_Backend_Action_Loader, \IWPML_AJAX_Action_Loader {

	public function create() {
		return new PendingOfferHooks();
	}
}
