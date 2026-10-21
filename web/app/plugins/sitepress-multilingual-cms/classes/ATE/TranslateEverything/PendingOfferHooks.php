<?php

namespace WPML\TM\ATE\TranslateEverything;

class PendingOfferHooks implements \IWPML_Action {

	public function add_hooks() {
		add_action( 'admin_init', array( PendingOffer::class, 'revertIfExpired' ) );
	}
}
