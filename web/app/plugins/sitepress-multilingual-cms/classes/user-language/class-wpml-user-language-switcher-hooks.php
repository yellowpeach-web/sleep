<?php

use WPML\API\Sanitize;

class WPML_User_Language_Switcher_Hooks {

	private $nonce_name = 'wpml_user_language_switcher';

	private $user_language_switcher_ui;
	private $user_language_switcher;

	public function __construct( &$WPML_User_Language_Switcher, &$WPML_User_Language_Switcher_UI ) {

		$this->user_language_switcher    = &$WPML_User_Language_Switcher;
		$this->user_language_switcher_ui = &$WPML_User_Language_Switcher_UI;

		add_action( 'wpml_user_language_switcher', array( $this, 'language_switcher_action' ), 10, 1 );
		\WPML\Request\Adapter\Ajax::register(
			'wpml_user_language_switcher_form_ajax',
			\WPML\Request\Policy\Policy::authenticated(
				\WPML\Request\Policy\Authenticity::actionNonce( $this->nonce_name, 'nonce' ),
				'sets the admin language of the current user only (principal-bound; the mail parameter is ignored)'
			),
			array( $this, 'language_switcher_form_ajax_callback' )
		);
	}

	public function language_switcher_action( $args ) {

		$defaults = array(
			'mail'              => null,
			'auto_refresh_page' => 0,
		);

		$args = array_merge( $defaults, $args );

		$model = $this->user_language_switcher->get_model( $args['mail'] );
		echo $this->user_language_switcher_ui->language_switcher( $args, $model );
	}

	public function language_switcher_form_ajax_callback() {
		$this->language_switcher_form_ajax();
	}

	public function language_switcher_form_ajax() {
		$language = Sanitize::stringProp( 'language', $_POST );
		$language = $this->user_language_switcher->sanitize( $language );

		$current = wp_get_current_user();
		$email   = $current && ! empty( $current->user_email ) ? $current->user_email : '';

		$posted_mail = filter_input( INPUT_POST, 'mail', FILTER_SANITIZE_EMAIL );
		if ( is_string( $posted_mail ) && '' !== $posted_mail && strcasecmp( $posted_mail, (string) $email ) !== 0 ) {
			wp_send_json_error();
		}

		$nonce = isset( $_POST['nonce'] ) && is_string( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		$valid = $this->is_valid_data( $nonce, $email );

		if ( ! $valid || ! $language ) {
			wp_send_json_error();
		}

		$saved_by_third_party = $updated = apply_filters( 'wpml_user_language_switcher_save', false, $email, $language );

		if ( ! $saved_by_third_party ) {
			$updated = $this->user_language_switcher->save_language_user_meta( $email, $language );
		}
		wp_send_json_success( $updated );
	}

	private function is_valid_data( $nonce, $email ) {
		return ( wp_verify_nonce( $nonce, $this->nonce_name ) && is_email( $email ) );
	}

}
