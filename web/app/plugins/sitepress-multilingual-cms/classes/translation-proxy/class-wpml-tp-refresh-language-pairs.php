<?php

class WPML_TP_Refresh_Language_Pairs {

	const AJAX_ACTION = 'wpml-tp-refresh-language-pairs';

	private $tp_api;

	public function __construct( WPML_TP_Project_API $wpml_tp_api ) {
		$this->tp_api = $wpml_tp_api;
	}

	public function add_hooks() {
		\WPML\Request\Adapter\Ajax::register( self::AJAX_ACTION, \WPML\Request\Policy\Policy::capability( 'manage_translations', \WPML\Request\Policy\Authenticity::actionNonce( self::AJAX_ACTION, 'nonce' ) ), array( $this, 'refresh_language_pairs' ) );
	}

	public function refresh_language_pairs() {
		if ( \WPML\Setup\Initializer::rejectSettingsMutationAjax() ) {
			return;
		}

		if ( $this->is_valid_request() ) {
			try {
				$this->tp_api->refresh_language_pairs();
				wp_send_json_success(
					array(
						'msg' => __( 'Language pairs refreshed', 'sitepress' ),
					)
				);
			} catch ( Exception $e ) {
				wp_send_json_error(
					array(
						'msg' => __( 'Language pairs not refreshed, please try again', 'sitepress' ),
					)
				);
			}
		} else {
			wp_send_json_error(
				array(
					/* translators: Title of the message shown when a request could not be carried out. */
					'msg' => __( 'Invalid Request', 'sitepress' ),
				)
			);
		}
	}

	private function is_valid_request() {
		return array_key_exists( 'nonce', $_POST ) &&
			   wp_verify_nonce( filter_var( $_POST['nonce'], FILTER_SANITIZE_FULL_SPECIAL_CHARS ), self::AJAX_ACTION );
	}
}
