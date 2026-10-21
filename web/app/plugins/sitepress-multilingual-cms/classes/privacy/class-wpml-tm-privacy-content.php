<?php

class WPML_TM_Privacy_Content extends WPML_Privacy_Content {

	protected function get_plugin_name() {
		return 'WPML Translation Management';
	}

	protected function get_privacy_policy() {
		return __( 'WPML Translation Management will send the email address and name of each manager and assigned translator as well as the content itself to <b>Advanced Translation Editor</b> and to the translation services which are used.', 'sitepress' );
	}
}
