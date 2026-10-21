<?php

use WPML\API\Sanitize;

class WPML_Languages_AJAX {
	private $sitepress;
	private $default_language;

	public function __construct( SitePress $sitepress ) {
		$this->sitepress        = $sitepress;
		$this->default_language = $this->sitepress->get_default_language();
	}

	public function ajax_hooks() {
		\WPML\Request\Adapter\Ajax::register( 'wpml_set_default_language', \WPML\Request\Policy\Policy::capability( 'manage_options', \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_set_default_language', 'nonce' ) ), array( $this, 'set_default_language_action' ) );
	}

	private function validate_ajax_action() {
		$action = Sanitize::stringProp( 'action', $_POST );
		$nonce  = Sanitize::stringProp( 'nonce', $_POST );

		return $action && $nonce && wp_verify_nonce( $nonce, $action ) && current_user_can( 'manage_options' );
	}

	private function reject_unrecoverable_settings_mutation() {
		if ( ! \WPML\Setup\Initializer::settingsAreUnrecoverable() ) {
			return false;
		}

		wp_send_json_error( \WPML\Setup\Initializer::getSettingsRecoveryError() );

		return true;
	}

	public function set_default_language_action() {
		if ( $this->reject_unrecoverable_settings_mutation() ) {
			return;
		}

		$failed   = true;
		$response = array();

		if ( $this->validate_ajax_action() ) {
			$previous_default     = $this->default_language;
			$new_default_language = filter_var( $_POST['language'], FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );

			$active_languages       = $this->sitepress->get_active_languages();
			$active_languages_codes = array_keys( $active_languages );

			if ( $new_default_language && in_array( $new_default_language, $active_languages_codes, true ) ) {
				$status = $this->sitepress->set_default_language( $new_default_language );
				if ( $status ) {
					$response['previousLanguage'] = $previous_default;
					$failed                       = false;
				}
				if ( 1 === $status ) {
					$response['message'] = __( 'WordPress language file (.mo) is missing. Keeping existing display language.', 'sitepress' );
				}
				if ( false === $status ) {
					$response['message'] = sprintf(
						/* translators: %s: the name of the language that is the default now. */
						__( 'Set a domain for %s in the URL format section first: in "A different domain per language" every language that is not the default needs one.', 'sitepress' ),
						isset( $active_languages[ $previous_default ]['display_name'] ) ? $active_languages[ $previous_default ]['display_name'] : $previous_default
					);
				}

				( new WPML_WP_Cache( WPML_URL_Cached_Converter::CACHE_GROUP ) )->flush_group_cache();
			}
		}

		if ( $failed ) {
			wp_send_json_error( $response );
		} else {
			wp_send_json_success( $response );
		}
	}
}
