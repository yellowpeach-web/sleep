<?php

namespace WPML\TM\AutomaticTranslation\Actions;

use WPML\LIB\WP\User;
use function WPML\Container\make;
use WPML\Setup\Option;

class ActionsFactory implements \IWPML_Backend_Action_Loader, \IWPML_Frontend_Action_Loader {

	public function create() {
		return \WPML_TM_ATE_Status::is_account_usable() && Option::shouldTranslateEverything()
			? make( Actions::class )
			: null;
	}
}
