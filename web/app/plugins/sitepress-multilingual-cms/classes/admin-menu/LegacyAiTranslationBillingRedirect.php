<?php

class WPML_Legacy_AI_Translation_Billing_Redirect implements IWPML_Backend_Action {

	public function add_hooks() {
		add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
	}


	public function maybe_redirect() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) !== 'GET' ) {
			return;
		}

		if ( wp_doing_ajax() ) {
			return;
		}

		global $pagenow;
		if ( $pagenow !== 'admin.php' ) {
			return;
		}

		if ( ! isset( $_GET['page'] ) || 'tm/menu/main.php' !== $_GET['page'] ) {
			return;
		}
		if ( ! isset( $_GET['sm'] ) || 'ate-ams' !== $_GET['sm'] ) {
			return;
		}

		$extra = $_GET;
		unset( $extra['page'], $extra['sm'] );
		$query = array_merge( array( 'page' => 'wpml-ai-translation-billing' ), $extra );

		wp_safe_redirect( admin_url( 'admin.php?' . http_build_query( $query ) ) );
		exit;
	}
}
