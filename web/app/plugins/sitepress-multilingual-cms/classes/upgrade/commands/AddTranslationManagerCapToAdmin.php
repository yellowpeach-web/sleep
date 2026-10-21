<?php

namespace WPML\Upgrade\Commands;

use WPML\Roles;

class AddTranslationManagerCapToAdmin extends \WPML_Upgrade_Run_All {

	protected function run() {
		Roles::ensure_manage_translations_cap();
		do_action( 'wpml_tm_ate_synchronize_managers' );
		wp_get_current_user()->get_role_caps();

		return true;
	}
}
