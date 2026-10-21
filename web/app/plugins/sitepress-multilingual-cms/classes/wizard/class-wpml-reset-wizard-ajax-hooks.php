<?php

namespace WPML\classes\wizard;

class WPML_Reset_Wizard_Ajax_Hooks {

	public function add_hooks() {
		\WPML\Request\Adapter\Ajax::register( 'reset_wpml_wizard', \WPML\Request\Policy\Policy::capability( 'manage_options', \WPML\Request\Policy\Authenticity::actionNonce( 'reset_wpml_wizard', 'nonce' ) ), [ $this, 'handle_reset_wizard_ajax' ] );
	}

	public function handle_reset_wizard_ajax() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'reset_wpml_wizard' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid security token.', 'sitepress' ),
				)
			);
			exit;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'sitepress' ),
				)
			);
			exit;
		}

		if ( \WPML\Setup\Initializer::settingsAreUnrecoverable() ) {
			wp_send_json_error( \WPML\Setup\Initializer::getSettingsRecoveryError() );

			return;
		}

		$reset_wizard = new WPML_Reset_Wizard();
		$result       = $reset_wizard->execute();

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to reset the wizard. Please try again.', 'sitepress' ),
				)
			);
		}

		exit;
	}
}
