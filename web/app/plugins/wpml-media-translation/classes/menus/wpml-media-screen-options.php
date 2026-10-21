<?php

class WPML_Media_Screen_Options implements IWPML_Action {

	const LEGACY_MEDIA_PAGE_LOAD_HOOK = 'load-wpml_page_wpml-media';

	const TRANSLATIONS_PAGE_LOAD_HOOK = 'load-toplevel_page_tm/menu/main';

	const MEDIA_TAB = 'media';

	private $options = array();

	public function __construct( $options ) {
		$this->options = $options;
	}

	public function add_hooks() {
		add_action( self::LEGACY_MEDIA_PAGE_LOAD_HOOK, array( $this, 'add_options' ) );
		add_action( self::TRANSLATIONS_PAGE_LOAD_HOOK, array( $this, 'add_options_on_media_tab' ) );
		add_filter( 'set-screen-option', array( $this, 'set_screen_option' ), 10, 3 );
	}

	public function add_options_on_media_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';

		if ( self::MEDIA_TAB !== $tab ) {
			return;
		}

		$this->add_options();
	}

	public function add_options() {
		foreach ( $this->options as $option ) {
			if ( isset( $option['args']['label'] ) ) {
				$option['args']['label'] = __( $option['args']['label'], 'wpml-media' );
			}
			add_screen_option( $option['key'], $option['args'] );
		}
	}

	public function set_screen_option( $status, $option_name, $value ) {
		if ( $this->is_valid_option( $option_name ) ) {
			update_option( $option_name, $value );
		}
	}

	private function is_valid_option( $option_name ) {
		$valid = false;
		foreach ( $this->options as $option ) {
			if ( $option_name === $option['args']['option'] ) {
				$valid = true;
				break;
			}
		}

		return $valid;
	}

}
