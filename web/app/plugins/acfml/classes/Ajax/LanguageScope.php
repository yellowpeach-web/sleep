<?php

namespace ACFML\Ajax;

class LanguageScope implements \IWPML_AJAX_Action {

	const ACF_COMPATIBILITY_CLASS = 'ACF_WPML_Compatibility';

	const OPEN_PRIORITY = 10;

	const CLOSE_PRIORITY = 0;

	private $openScopes = 0;

	public function add_hooks() {
		if ( ! $this->removeAcfWholeRequestSwitch() ) {
			return;
		}

		add_action( 'acf/verify_ajax', [ $this, 'openLanguageScope' ], self::OPEN_PRIORITY );
	}

	public function openLanguageScope() {
		$language = $this->requestedLanguage();

		if ( null === $language ) {
			return;
		}

		if ( 0 === $this->openScopes ) {
			add_action( 'shutdown', [ $this, 'closeLanguageScopes' ], self::CLOSE_PRIORITY );
		}

		++$this->openScopes;

		do_action( 'wpml_switch_language', $language );
	}

	public function closeLanguageScopes() {
		while ( $this->openScopes > 0 ) {
			--$this->openScopes;

			do_action( 'wpml_switch_language', null );
		}
	}

	private function requestedLanguage() {
		if ( ! isset( $_REQUEST['lang'] ) ) {
			return null;
		}

		$language = sanitize_text_field( wp_unslash( $_REQUEST['lang'] ) );

		return '' === $language ? null : $language;
	}

	private function removeAcfWholeRequestSwitch() {
		if ( ! class_exists( self::ACF_COMPATIBILITY_CLASS, false ) || ! function_exists( 'acf_get_instance' ) ) {
			return false;
		}

		$acfCompatibility = acf_get_instance( self::ACF_COMPATIBILITY_CLASS );

		return remove_action( 'acf/verify_ajax', [ $acfCompatibility, 'verify_ajax' ], self::OPEN_PRIORITY );
	}
}
