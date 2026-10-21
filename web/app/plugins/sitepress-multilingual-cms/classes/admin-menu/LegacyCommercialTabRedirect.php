<?php

class WPML_Legacy_Commercial_Tab_Redirect implements IWPML_Backend_Action {

	public function add_hooks() {
		add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
	}


	const INSTALLER_ACTION_PARAMS = array( 'validate_repository' );

	public function maybe_redirect() {
		if ( ! $this->should_redirect() ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wpml-activate-update' ) );
		exit;
	}


	public function should_redirect() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) !== 'GET' ) {
			return false;
		}

		if ( wp_doing_ajax() ) {
			return false;
		}

		global $pagenow;
		if ( 'plugin-install.php' !== $pagenow ) {
			return false;
		}

		if ( ! isset( $_GET['tab'] ) || 'commercial' !== $_GET['tab'] ) {
			return false;
		}

		foreach ( self::INSTALLER_ACTION_PARAMS as $param ) {
			if ( isset( $_GET[ $param ] ) ) {
				return false;
			}
		}

		return true;
	}
}
