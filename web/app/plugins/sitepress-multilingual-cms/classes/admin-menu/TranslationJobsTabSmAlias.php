<?php

class WPML_Translation_Jobs_Tab_Sm_Alias implements IWPML_Backend_Action {

	const TRANSLATIONS_PAGE_SLUG = 'tm/menu/main.php';
	const JOBS_TAB               = 'jobs';
	const LEGACY_SM_VALUE        = 'jobs';

	private $original_sm;

	private $had_sm;

	public function add_hooks() {
		if ( ! $this->is_jobs_tab_request() ) {
			return;
		}

		add_action( 'wpml_loaded', array( $this, 'apply_alias' ), 0 );
		add_action( 'wpml_loaded', array( $this, 'restore_alias' ), 999 );

		add_action( 'wp_loaded', array( $this, 'apply_alias' ), 0 );
		add_action( 'wp_loaded', array( $this, 'restore_alias' ), 999 );
	}

	private function is_jobs_tab_request() {
		$page = \WPML\SuperGlobals\Request::page();
		$tab  = \WPML\SuperGlobals\Request::param( 'tab' );

		return self::TRANSLATIONS_PAGE_SLUG === $page && self::JOBS_TAB === $tab;
	}

	public function apply_alias() {
		$this->had_sm      = isset( $_GET['sm'] );
		$this->original_sm = $this->had_sm ? \WPML\SuperGlobals\Request::param( 'sm' ) : null;
		$_GET['sm']        = self::LEGACY_SM_VALUE;
	}

	public function restore_alias() {
		if ( null === $this->had_sm ) {
			return;
		}
		if ( ! $this->had_sm ) {
			unset( $_GET['sm'] );
			$this->had_sm = null;
			return;
		}
		$_GET['sm']   = $this->original_sm;
		$this->had_sm = null;
	}
}
