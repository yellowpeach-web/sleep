<?php

class WPML_Legacy_Languages_Menu_Highlight implements IWPML_Backend_Action {

	const LEGACY_LANGUAGES_SLUG  = 'sitepress-multilingual-cms/menu/languages.php';
	const SETTINGS_PAGE_SLUG     = 'tm/menu/settings';
	const TRANSLATIONS_PAGE_SLUG = 'tm/menu/main.php';

	const HIDDEN_PARENT_SLUG = 'wpml-hidden-legacy-menu';

	public function add_hooks() {
		if ( ! $this->is_legacy_languages_request() ) {
			return;
		}

		add_filter( 'parent_file', array( $this, 'highlight_settings_parent' ) );
		add_filter( 'submenu_file', array( $this, 'highlight_settings_submenu' ) );
	}


	public function highlight_settings_parent( $parent_file ) {
		if ( ! isset( $GLOBALS['_wp_real_parent_file'] ) || ! is_array( $GLOBALS['_wp_real_parent_file'] ) ) {
			$GLOBALS['_wp_real_parent_file'] = array();
		}
		$GLOBALS['_wp_real_parent_file'][ self::HIDDEN_PARENT_SLUG ] = self::TRANSLATIONS_PAGE_SLUG;

		return self::TRANSLATIONS_PAGE_SLUG;
	}


	public function highlight_settings_submenu( $submenu_file ) {
		return self::SETTINGS_PAGE_SLUG;
	}


	private function is_legacy_languages_request() {
		$page = \WPML\SuperGlobals\Request::page();

		return self::LEGACY_LANGUAGES_SLUG === $page;
	}
}
