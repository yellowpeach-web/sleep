<?php

class WPML_Core_Privacy_Content extends WPML_Privacy_Content {

	protected function get_plugin_name() {
		return 'WPML';
	}

	protected function get_privacy_policy() {
		return array(
			__( 'WPML uses cookies to identify the visitor’s current language, the last visited language and the language of users who have logged in.', 'sitepress' ),
			sprintf(
				/* translators: Sentence in the privacy policy text WPML suggests for the site. %s: the name of a cookie, _icl_visitor_chosen_lang. */
				__( 'The %s cookie keeps the language a visitor picked in the language switcher for the number of days set by the site, and is used to show the site in that language on their next visit.', 'sitepress' ),
				WPML_Browser_Redirect::CHOSEN_LANGUAGE_COOKIE
			),
			__( 'While you use the plugin, WPML will share data regarding the site through Installer. No data from the user itself will be shared.', 'sitepress' ),
		);
	}

}
