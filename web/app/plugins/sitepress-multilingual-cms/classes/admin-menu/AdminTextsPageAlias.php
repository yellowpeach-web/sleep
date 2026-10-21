<?php

class WPML_Admin_Texts_Page_Alias implements IWPML_Backend_Action {

	const STANDALONE_PAGE_SLUG   = 'wpml-admin-texts-translation';
	const LEGACY_ST_SLUG         = 'wpml-string-translation/menu/string-translation.php';
	const LEGACY_TROP_VALUE      = '1';
	const TRANSLATIONS_PAGE_SLUG = 'tm/menu/main.php';

	private $original_page;

	private $original_trop;

	private $had_page;

	private $had_trop;

	private $applied = false;

	public function add_hooks() {
		if ( ! $this->is_admin_texts_page_request() ) {
			return;
		}

		add_action( 'wpml_loaded', array( $this, 'apply_alias' ), 0 );
		add_action( 'wpml_loaded', array( $this, 'restore_alias' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scanning_progress_dialog_assets' ) );
		add_action( 'admin_footer', array( $this, 'render_scanning_progress_dialog' ) );

		add_filter( 'parent_file', array( $this, 'highlight_translations_parent' ) );
		add_filter( 'submenu_file', array( $this, 'highlight_translations_submenu' ) );
	}


	public function highlight_translations_parent( $parent_file ) {
		if ( ! isset( $GLOBALS['_wp_real_parent_file'] ) || ! is_array( $GLOBALS['_wp_real_parent_file'] ) ) {
			$GLOBALS['_wp_real_parent_file'] = array();
		}
		$GLOBALS['_wp_real_parent_file']['wpml-translations-hidden'] = self::TRANSLATIONS_PAGE_SLUG;

		return self::TRANSLATIONS_PAGE_SLUG;
	}


	public function highlight_translations_submenu( $submenu_file ) {
		return self::TRANSLATIONS_PAGE_SLUG;
	}

	private function is_admin_texts_page_request() {
		$page = \WPML\SuperGlobals\Request::page();

		return self::STANDALONE_PAGE_SLUG === $page;
	}

	public function enqueue_scanning_progress_dialog_assets() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_style( 'wp-jquery-ui-dialog' );
	}

	public function render_scanning_progress_dialog() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$ui = new WPML_Theme_Plugin_Localization_UI();

		echo $ui->renderTemplate(
			'scanning-progress.twig',
			array(
				'scanning_progress_msg'  => __( "Scanning now, please don't close this page.", 'sitepress' ),
				/* translators: Heading above the list of texts found when the theme or a plugin was gone through. */
				'scanning_results_title' => __( 'Scanning Results', 'sitepress' ),
			)
		);
	}

	public function apply_alias() {
		$this->had_page      = isset( $_GET['page'] );
		$this->original_page = $this->had_page ? \WPML\SuperGlobals\Request::page() : null;
		$this->had_trop      = isset( $_GET['trop'] );
		$this->original_trop = $this->had_trop ? \WPML\SuperGlobals\Request::param( 'trop' ) : null;

		$_GET['page']  = self::LEGACY_ST_SLUG;
		$_GET['trop']  = self::LEGACY_TROP_VALUE;
		$this->applied = true;
	}

	public function restore_alias() {
		if ( ! $this->applied ) {
			return;
		}

		if ( $this->had_page ) {
			$_GET['page'] = $this->original_page;
		} else {
			unset( $_GET['page'] );
		}

		if ( $this->had_trop ) {
			$_GET['trop'] = $this->original_trop;
		} else {
			unset( $_GET['trop'] );
		}

		$this->applied = false;
	}
}
