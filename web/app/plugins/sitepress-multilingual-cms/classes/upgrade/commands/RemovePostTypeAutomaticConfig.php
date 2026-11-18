<?php

namespace WPML\Upgrade\Commands;

use WPML\Settings\PostType\Automatic;
use WPML\WP\OptionManager;

class RemovePostTypeAutomaticConfig extends \WPML_Upgrade_Run_All {

	protected function run() {
		if ( OptionManager::getOr( [], Automatic::GROUP, Automatic::FROM_CONFIG ) ) {
			OptionManager::update( Automatic::GROUP, Automatic::FROM_CONFIG, [] );
		}

		return true;
	}
}
