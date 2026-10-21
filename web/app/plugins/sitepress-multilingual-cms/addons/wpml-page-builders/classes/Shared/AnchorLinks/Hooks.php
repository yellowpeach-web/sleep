<?php

namespace WPML\PB\AnchorLinks;

class Hooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	public function add_hooks() {
		add_filter( 'wpml_pb_update_post_translations', [ Sync::class, 'run' ], 10, 3 );
	}
}
