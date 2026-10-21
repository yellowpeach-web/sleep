<?php

class WPML_Pre_Setup_Upgrade_Loader_Factory implements IWPML_Backend_Action_Loader {

	public function create() {
		global $sitepress;

		if (
			WPML_Settings_Failsafe_Loader::isUnrecoverable()
			|| ! $sitepress instanceof SitePress
			|| $sitepress->is_setup_complete()
		) {
			return null;
		}

		return new WPML_Pre_Setup_Upgrade_Loader(
			new WPML_Upgrade_Loader(
				$sitepress,
				wpml_get_upgrade_schema(),
				wpml_load_settings_helper(),
				wpml_get_admin_notices(),
				wpml_get_upgrade_command_factory()
			),
			$sitepress,
			wpml_get_upgrade_command_factory()
		);
	}
}
