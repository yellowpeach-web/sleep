<?php

class WPML_Strings_Tab_Page_Alias implements IWPML_Backend_Action {

	const TRANSLATIONS_PAGE_SLUG = 'tm/menu/main.php';
	const STRINGS_TAB            = 'strings';
	const LEGACY_ST_SLUG         = 'wpml-string-translation/menu/string-translation.php';

	private $original_page;

	private $had_page;

	public function add_hooks() {
		if ( ! $this->is_strings_tab_request() ) {
			return;
		}

		add_action( 'wpml_loaded', array( $this, 'apply_alias' ), 0 );
		add_action( 'wpml_loaded', array( $this, 'restore_alias' ), 999 );

		add_action( 'init', array( $this, 'apply_alias' ), 1 );
		add_action( 'init', array( $this, 'restore_alias' ), 999 );
	}

	private function is_strings_tab_request() {
		$page = \WPML\SuperGlobals\Request::page();
		$tab  = \WPML\SuperGlobals\Request::param( 'tab' );

		return self::TRANSLATIONS_PAGE_SLUG === $page && self::STRINGS_TAB === $tab;
	}

	public function apply_alias() {
		$this->had_page      = isset( $_GET['page'] );
		$this->original_page = $this->had_page ? \WPML\SuperGlobals\Request::page() : null;
		$_GET['page']        = self::LEGACY_ST_SLUG;
	}

	public function restore_alias() {
		if ( ! $this->had_page ) {
			unset( $_GET['page'] );
			return;
		}
		$_GET['page'] = $this->original_page;
	}
}
