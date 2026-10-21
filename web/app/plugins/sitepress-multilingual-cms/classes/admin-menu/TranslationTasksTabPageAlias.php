<?php

use WPML\TM\Menu\TranslationQueue\TranslationQueuePage;

class WPML_Translation_Tasks_Tab_Page_Alias implements IWPML_Backend_Action {

	const TRANSLATIONS_PAGE_SLUG = TranslationQueuePage::SLUG;
	const TASKS_TAB              = TranslationQueuePage::TAB;
	const LEGACY_QUEUE_SLUG      = TranslationQueuePage::LEGACY_SLUG;

	private $original_page;

	private $had_page;

	public function add_hooks() {
		if ( ! $this->is_translations_page_candidate() ) {
			return;
		}

		add_action( 'wpml_loaded', array( $this, 'apply_alias' ), 0 );
		add_action( 'wpml_loaded', array( $this, 'restore_alias' ), 999 );

		add_action( 'wp_loaded', array( $this, 'apply_alias' ), 0 );
		add_action( 'wp_loaded', array( $this, 'restore_alias' ), 999 );
	}

	private function is_translations_page_candidate() {
		return TranslationQueuePage::hostsQueue( [
			'page' => \WPML\SuperGlobals\Request::page(),
			'tab'  => \WPML\SuperGlobals\Request::param( 'tab' ),
		] );
	}

	private function should_apply() {
		$tab = \WPML\SuperGlobals\Request::param( 'tab' );

		if ( self::TASKS_TAB === $tab ) {
			return true;
		}

		if ( '' === $tab && ! current_user_can( 'manage_translations' ) && current_user_can( 'translate' ) ) {
			return true;
		}

		return false;
	}

	public function apply_alias() {
		if ( ! $this->should_apply() ) {
			return;
		}
		$this->had_page      = isset( $_GET['page'] );
		$this->original_page = $this->had_page ? \WPML\SuperGlobals\Request::page() : null;
		$_GET['page']        = self::LEGACY_QUEUE_SLUG;
	}

	public function restore_alias() {
		if ( null === $this->had_page ) {
			return;
		}
		if ( ! $this->had_page ) {
			unset( $_GET['page'] );
			$this->had_page = null;
			return;
		}
		$_GET['page']   = $this->original_page;
		$this->had_page = null;
	}
}
