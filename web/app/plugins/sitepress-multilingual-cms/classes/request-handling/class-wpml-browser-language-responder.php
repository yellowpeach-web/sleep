<?php

class WPML_Browser_Language_Responder {

	public static function add_hooks() {
		add_action( 'init', array( __CLASS__, 'maybe_respond' ), 15 );
	}

	public static function maybe_respond() {
		if ( ! isset( $_GET['icl_ajx_action'] ) || 'get_browser_language' !== $_GET['icl_ajx_action'] ) {
			return;
		}

		$http_accept_language            = (string) filter_var( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) : '', FILTER_SANITIZE_SPECIAL_CHARS );
		$accepted_languages              = explode( ';', $http_accept_language );
		$default_accepted_language       = $accepted_languages[0];
		$default_accepted_language_codes = explode( ',', $default_accepted_language );
		wp_send_json_success( $default_accepted_language_codes );
	}
}
