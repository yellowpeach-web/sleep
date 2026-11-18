<?php

namespace WPML\Upgrade\Commands;

class ResetResolvedUrls extends \WPML_Upgrade_Run_All {

	protected function run() {
		\WPML_Absolute_Url_Persisted::get_instance()->reset();

		return true;
	}
}
